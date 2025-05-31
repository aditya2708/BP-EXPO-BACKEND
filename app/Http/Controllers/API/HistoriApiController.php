<?php

namespace App\Http\Controllers\API;

use App\Models\Histori;
use App\Models\Anak;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Resources\Histori\HistoriCollection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class HistoriApiController extends Controller
{
    /**
     * Menampilkan daftar Histori dengan pagination
     */
    public function index(Request $request)
    {
        $query = Histori::query();

        // Filter berdasarkan id_anak jika ada parameter
        if ($request->has('id_anak')) {
            $query->where('id_anak', $request->id_anak);
        }

        // Filter berdasarkan jenis_histori jika ada parameter
        if ($request->has('jenis_histori')) {
            $query->where('jenis_histori', $request->jenis_histori);
        }

        // Hitung total histori
        $totalHistori = Histori::count();

        // Eager loading relasi anak
        $histori = $query->with('anak')
                         ->latest()
                         ->paginate($request->per_page ?? 10);

        return response()->json([
            'success'    => true,
            'message'    => 'Daftar Histori',
            'data'       => HistoriCollection::collection($histori),
            'pagination' => [
                'total'         => $histori->total(),
                'per_page'      => $histori->perPage(),
                'current_page'  => $histori->currentPage(),
                'last_page'     => $histori->lastPage(),
                'from'          => $histori->firstItem(),
                'to'            => $histori->lastItem()
            ],
            'summary'   => [
                'total_histori'   => $totalHistori
            ]
        ], 200);
    }

    /**
     * Menampilkan detail Histori berdasarkan ID
     */
    public function show($id)
    {
        $histori = Histori::with('anak')
                           ->findOrFail($id);

        return response()->json([
            'success' => true,
            'message' => 'Detail Histori',
            'data'    => new HistoriCollection($histori)
        ], 200);
    }

    /**
     * Membuat Histori baru
     */
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'id_anak' => 'required|exists:anak,id_anak',
            'jenis_histori' => 'required|string|max:255',
            'nama_histori' => 'required|string|max:255',
            'di_opname' => 'nullable|string|max:255',
            'dirawat_id' => 'nullable|exists:anak,id_anak',
            'tanggal' => 'nullable|date',
            'foto' => 'nullable|image|max:2048', // maksimal 2MB
            'is_read' => 'nullable|boolean'
        ]);

        $histori = new Histori();
        $histori->fill($validatedData);

        // Handle foto upload
        if ($request->hasFile('foto')) {
            $file = $request->file('foto');
            $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs("Histori/{$validatedData['id_anak']}", $filename, 'public');
            $histori->foto = $filename;
        }

        $histori->tanggal = $histori->tanggal ?? now();
        $histori->save();

        return response()->json([
            'success' => true,
            'message' => 'Histori berhasil dibuat',
            'data'    => new HistoriCollection($histori)
        ], 201);
    }

    /**
     * Memperbarui Histori
     */
    public function update(Request $request, $id)
    {
        $histori = Histori::findOrFail($id);

        $validatedData = $request->validate([
            'id_anak' => 'sometimes|exists:anak,id_anak',
            'jenis_histori' => 'sometimes|string|max:255',
            'nama_histori' => 'sometimes|string|max:255',
            'di_opname' => 'nullable|string|max:255',
            'dirawat_id' => 'nullable|exists:anak,id_anak',
            'tanggal' => 'nullable|date',
            'foto' => 'nullable|image|max:2048', // maksimal 2MB
            'is_read' => 'nullable|boolean'
        ]);

        // Handle foto upload
        if ($request->hasFile('foto')) {
            // Hapus foto lama jika ada
            if ($histori->foto) {
                Storage::disk('public')->delete("Histori/{$histori->id_anak}/{$histori->foto}");
            }

            $file = $request->file('foto');
            $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs("Histori/" . (isset($validatedData['id_anak']) ? $validatedData['id_anak'] : $histori->id_anak), $filename, 'public');
            $validatedData['foto'] = $filename;
        }

        $histori->update($validatedData);

        return response()->json([
            'success' => true,
            'message' => 'Histori berhasil diperbarui',
            'data'    => new HistoriCollection($histori)
        ], 200);
    }

    /**
     * Menghapus Histori
     */
    public function destroy($id)
    {
        $histori = Histori::findOrFail($id);
        
        // Hapus foto jika ada
        if ($histori->foto) {
            Storage::disk('public')->delete("Histori/{$histori->id_anak}/{$histori->foto}");
        }

        $histori->delete();

        return response()->json([
            'success' => true,
            'message' => 'Histori berhasil dihapus'
        ], 200);
    }
}