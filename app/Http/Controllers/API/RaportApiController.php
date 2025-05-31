<?php

namespace App\Http\Controllers\API;

use App\Models\Raport;
use App\Models\Anak;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Resources\Raport\RaportCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class RaportApiController extends Controller
{
    /**
     * Menampilkan daftar Raport dengan pagination
     */
    public function index(Request $request)
    {
        $query = Raport::query();

        // Filter berdasarkan id_anak
        if ($request->has('id_anak')) {
            $query->where('id_anak', $request->id_anak);
        }

        // Filter berdasarkan semester
        if ($request->has('semester')) {
            $query->where('semester', $request->semester);
        }

        // Filter berdasarkan tingkat
        if ($request->has('tingkat')) {
            $query->where('tingkat', $request->tingkat);
        }

        // Filter berdasarkan tahun
        if ($request->has('tahun')) {
            $query->whereYear('tanggal', $request->tahun);
        }

        // Hitung total raport
        $totalRaport = $query->count();

        // Eager loading relasi anak dan foto rapor
        $raport = $query->with(['anak', 'fotoRapor'])
                        ->latest()
                        ->paginate($request->per_page ?? 10);

        return response()->json([
            'success'    => true,
            'message'    => 'Daftar Raport',
            'data'       => RaportCollection::collection($raport),
            'pagination' => [
                'total'         => $raport->total(),
                'per_page'      => $raport->perPage(),
                'current_page'  => $raport->currentPage(),
                'last_page'     => $raport->lastPage(),
                'from'          => $raport->firstItem(),
                'to'            => $raport->lastItem()
            ],
            'summary'   => [
                'total_raport'   => $totalRaport
            ]
        ], 200);
    }

    /**
     * Menampilkan detail Raport berdasarkan ID
     */
    public function show($id)
    {
        $raport = Raport::with(['anak', 'fotoRapor'])
                        ->findOrFail($id);

        return response()->json([
            'success' => true,
            'message' => 'Detail Raport',
            'data'    => new RaportCollection($raport)
        ], 200);
    }

    /**
     * Membuat Raport baru
     */
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'id_anak' => 'required|exists:anak,id_anak',
            'tingkat' => 'required|string|max:50',
            'kelas' => 'required|string|max:50',
            'nilai_max' => 'nullable|numeric',
            'nilai_min' => 'nullable|numeric',
            'nilai_rata_rata' => 'nullable|numeric',
            'semester' => 'required|string|max:20',
            'tanggal' => 'required|date',
            'foto_rapor' => 'sometimes|array',
            'foto_rapor.*' => 'image|mimes:jpeg,png,jpg|max:2048'
        ]);

        // Validate that anak belongs to the authenticated admin_shelter's shelter
        $anak = Anak::findOrFail($validatedData['id_anak']);

        // Create raport
        $raport = Raport::create([
            'id_anak' => $validatedData['id_anak'],
            'tingkat' => $validatedData['tingkat'],
            'kelas' => $validatedData['kelas'],
            'nilai_max' => $validatedData['nilai_max'] ?? null,
            'nilai_min' => $validatedData['nilai_min'] ?? null,
            'nilai_rata_rata' => $validatedData['nilai_rata_rata'] ?? null,
            'semester' => $validatedData['semester'],
            'tanggal' => $validatedData['tanggal'],
            'is_read' => 'tidak'
        ]);

        // Handle foto rapor upload
        if ($request->hasFile('foto_rapor')) {
            foreach ($request->file('foto_rapor') as $foto) {
                $path = $foto->store("Raport/{$raport->id_raport}", 'public');
                
                $raport->fotoRapor()->create([
                    'nama' => basename($path)
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Raport berhasil dibuat',
            'data'    => new RaportCollection(Raport::with(['anak', 'fotoRapor'])->find($raport->id_raport))
        ], 201);
    }

    /**
     * Memperbarui Raport
     */
    public function update(Request $request, $id)
    {
        $raport = Raport::findOrFail($id);

        $validatedData = $request->validate([
            'id_anak' => 'sometimes|exists:anak,id_anak',
            'tingkat' => 'sometimes|string|max:50',
            'kelas' => 'sometimes|string|max:50',
            'nilai_max' => 'nullable|numeric',
            'nilai_min' => 'nullable|numeric',
            'nilai_rata_rata' => 'nullable|numeric',
            'semester' => 'sometimes|string|max:20',
            'tanggal' => 'sometimes|date',
            'foto_rapor' => 'sometimes|array',
            'foto_rapor.*' => 'image|mimes:jpeg,png,jpg|max:2048',
            'hapus_foto' => 'sometimes|array' // Optional array of foto rapor IDs to delete
        ]);

        // Update raport data
        $updateData = collect($validatedData)->except(['foto_rapor', 'hapus_foto'])->toArray();
        $raport->update($updateData);

        // Handle hapus foto
        if ($request->has('hapus_foto')) {
            foreach ($validatedData['hapus_foto'] as $fotoId) {
                $fotoRapor = $raport->fotoRapor()->find($fotoId);
                if ($fotoRapor) {
                    // Hapus file dari storage
                    if (Storage::disk('public')->exists("Raport/{$raport->id_raport}/{$fotoRapor->nama}")) {
                        Storage::disk('public')->delete("Raport/{$raport->id_raport}/{$fotoRapor->nama}");
                    }
                    // Hapus record dari database
                    $fotoRapor->delete();
                }
            }
        }

        // Handle tambah foto rapor
        if ($request->hasFile('foto_rapor')) {
            foreach ($request->file('foto_rapor') as $foto) {
                $path = $foto->store("Raport/{$raport->id_raport}", 'public');
                
                $raport->fotoRapor()->create([
                    'nama' => basename($path)
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Raport berhasil diperbarui',
            'data'    => new RaportCollection(Raport::with(['anak', 'fotoRapor'])->find($raport->id_raport))
        ], 200);
    }

    /**
     * Menghapus Raport
     */
    public function destroy($id)
    {
        $raport = Raport::findOrFail($id);
        
        // Hapus foto-foto terkait
        $fotoRapor = $raport->fotoRapor;
        foreach ($fotoRapor as $foto) {
            // Hapus file dari storage
            if (Storage::disk('public')->exists("Raport/{$raport->id_raport}/{$foto->nama}")) {
                Storage::disk('public')->delete("Raport/{$raport->id_raport}/{$foto->nama}");
            }
            // Hapus record dari database
            $foto->delete();
        }

        // Hapus raport
        $raport->delete();

        return response()->json([
            'success' => true,
            'message' => 'Raport berhasil dihapus'
        ], 200);
    }
}