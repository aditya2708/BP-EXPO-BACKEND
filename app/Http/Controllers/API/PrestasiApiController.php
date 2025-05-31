<?php

namespace App\Http\Controllers\API;

use App\Models\Prestasi;
use App\Models\Anak;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Resources\Prestasi\PrestasiCollection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PrestasiApiController extends Controller
{
    /**
     * Menampilkan daftar Prestasi dengan pagination
     */
    public function index(Request $request)
    {
        $query = Prestasi::query();

        // Filter berdasarkan id_anak jika ada parameter
        if ($request->has('id_anak')) {
            $query->where('id_anak', $request->id_anak);
        }

        // Filter berdasarkan jenis_prestasi jika ada parameter
        if ($request->has('jenis_prestasi')) {
            $query->where('jenis_prestasi', $request->jenis_prestasi);
        }

        // Filter berdasarkan level_prestasi jika ada parameter
        if ($request->has('level_prestasi')) {
            $query->where('level_prestasi', $request->level_prestasi);
        }

        // Hitung total prestasi
        $totalPrestasi = Prestasi::count();

        // Eager loading relasi anak
        $prestasi = $query->with('anak')
                          ->latest()
                          ->paginate($request->per_page ?? 10);

        return response()->json([
            'success'    => true,
            'message'    => 'Daftar Prestasi',
            'data'       => PrestasiCollection::collection($prestasi),
            'pagination' => [
                'total'         => $prestasi->total(),
                'per_page'      => $prestasi->perPage(),
                'current_page'  => $prestasi->currentPage(),
                'last_page'     => $prestasi->lastPage(),
                'from'          => $prestasi->firstItem(),
                'to'            => $prestasi->lastItem()
            ],
            'summary'   => [
                'total_prestasi'   => $totalPrestasi
            ]
        ], 200);
    }

    /**
     * Menampilkan detail Prestasi berdasarkan ID
     */
    public function show($id)
    {
        $prestasi = Prestasi::with('anak')
                            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'message' => 'Detail Prestasi',
            'data'    => new PrestasiCollection($prestasi)
        ], 200);
    }

    /**
     * Membuat Prestasi baru
     */
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'id_anak' => 'required|exists:anak,id_anak',
            'jenis_prestasi' => 'required|string|max:255',
            'level_prestasi' => 'required|string|max:255',
            'nama_prestasi' => 'required|string|max:255',
            'foto' => 'nullable|image|max:2048', // maksimal 2MB
            'tgl_upload' => 'nullable|date',
            'is_read' => 'nullable|boolean'
        ]);

        $prestasi = new Prestasi();
        $prestasi->fill($validatedData);

        // Handle foto upload
        if ($request->hasFile('foto')) {
            $file = $request->file('foto');
            $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs("Prestasi/{$validatedData['id_anak']}", $filename, 'public');
            $prestasi->foto = $filename;
        }

        $prestasi->tgl_upload = $prestasi->tgl_upload ?? now();
        $prestasi->save();

        return response()->json([
            'success' => true,
            'message' => 'Prestasi berhasil dibuat',
            'data'    => new PrestasiCollection($prestasi)
        ], 201);
    }

    /**
     * Memperbarui Prestasi
     */
    public function update(Request $request, $id)
    {
        $prestasi = Prestasi::findOrFail($id);

        $validatedData = $request->validate([
            'id_anak' => 'sometimes|exists:anak,id_anak',
            'jenis_prestasi' => 'sometimes|string|max:255',
            'level_prestasi' => 'sometimes|string|max:255',
            'nama_prestasi' => 'sometimes|string|max:255',
            'foto' => 'nullable|image|max:2048', // maksimal 2MB
            'tgl_upload' => 'nullable|date',
            'is_read' => 'nullable|boolean'
        ]);

        // Handle foto upload
        if ($request->hasFile('foto')) {
            // Hapus foto lama jika ada
            if ($prestasi->foto) {
                Storage::disk('public')->delete("Prestasi/{$prestasi->id_anak}/{$prestasi->foto}");
            }

            $file = $request->file('foto');
            $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
           $path = $file->storeAs("Prestasi/" . (isset($validatedData['id_anak']) ? $validatedData['id_anak'] : $prestasi->id_anak), $filename, 'public');
            $validatedData['foto'] = $filename;
        }

        $prestasi->update($validatedData);

        return response()->json([
            'success' => true,
            'message' => 'Prestasi berhasil diperbarui',
            'data'    => new PrestasiCollection($prestasi)
        ], 200);
    }

    /**
     * Menghapus Prestasi
     */
    public function destroy($id)
    {
        $prestasi = Prestasi::findOrFail($id);
        
        // Hapus foto jika ada
        if ($prestasi->foto) {
            Storage::disk('public')->delete("Prestasi/{$prestasi->id_anak}/{$prestasi->foto}");
        }

        $prestasi->delete();

        return response()->json([
            'success' => true,
            'message' => 'Prestasi berhasil dihapus'
        ], 200);
    }
}