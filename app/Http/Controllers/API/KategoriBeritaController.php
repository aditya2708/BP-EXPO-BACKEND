<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\KategoriBerita;
use App\Http\Resources\Kategori\KategoriCollection;
use Illuminate\Support\Facades\Validator;

class KategoriBeritaController extends Controller
{
    /**
     * Menampilkan daftar kategori berita dengan pencarian & pagination
     */
    public function index(Request $request)
    {
        $query = KategoriBerita::query();
    
        // Filter berdasarkan 'name_kategori'
        if ($request->has('search')) {
            $query->where('name_kategori', 'like', '%' . $request->search . '%');
        }
        
        if ($request->has('status_kategori_berita')) {
            $query->where('status_kategori_berita', $request->status_kategori_berita);
        }
    
        // Pagination (default 10 per page)
        $kategori = $query->latest()->paginate($request->per_page ?? 10);
    
        return response()->json([
            'success' => true,
            'message' => 'Daftar Kategori Berita',
            'data' => new KategoriCollection($kategori),
            'pagination' => [
                'total' => $kategori->total(),
                'per_page' => $kategori->perPage(),
                'current_page' => $kategori->currentPage(),
                'last_page' => $kategori->lastPage(),
                'from' => $kategori->firstItem(),
                'to' => $kategori->lastItem()
            ]
        ], 200);
    }
    
     public function toggleStatus($id)
    {
        $kategori = KategoriBerita::find($id);
    
        if (!$kategori) {
            return response()->json([
                'success' => false,
                'message' => 'Kategori Berita tidak ditemukan'
            ], 404);
        }
    
        // Toggle status berita antara "Aktif" dan "Tidak Aktif"
        $kategori->status_kategori_berita = $kategori->status_kategori_berita === KategoriBerita::STATUS_AKTIF 
            ? KategoriBerita::STATUS_NON_AKTIF 
            : KategoriBerita::STATUS_AKTIF;
        $kategori->save();
    
        return response()->json([
            'success' => true,
            'message' => 'Status Kategori Berita berhasil diperbarui',
            'data' => [
                'id' => $kategori->id,
                'name_kategori' => $kategori->name_kategori,
                'status_kategori_berita' => $kategori->status_kategori_berita
            ]
        ], 200);
    }

    /**
     * Menampilkan detail kategori berita berdasarkan ID
     */
    public function show($id)
    {
        $kategori = KategoriBerita::find($id);

        if (!$kategori) {
            return response()->json([
                'success' => false,
                'message' => 'Kategori tidak ditemukan'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Detail Kategori Berita',
            'data' => $kategori
        ], 200);
    }

    /**
     * Menambahkan kategori berita baru
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name_kategori' => 'required|string|max:255|unique:kategori_berita,name_kategori',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi Gagal',
                'errors' => $validator->errors()
            ], 400);
        }

        $kategori = KategoriBerita::create([
            'name_kategori' => $request->name_kategori,
            'status_kategori_berita' => KategoriBerita::STATUS_AKTIF,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Kategori Berita Berhasil Ditambahkan',
            'data' => $kategori
        ], 201);
    }

    /**
     * Mengupdate kategori berita
     */
    public function update(Request $request, $id) 
    {
        // Pastikan kategori dengan ID tersebut ada di database
        $kategori = KategoriBerita::find($id);
        
        if (!$kategori) {
            return response()->json([
                'success' => false,
                'message' => 'Kategori tidak ditemukan'
            ], 404);
        }
    
        // Validasi input
        $validator = Validator::make($request->all(), [
            'name_kategori' => 'required|string|max:255|unique:kategori_berita,name_kategori,' . $id,
        ]);
    
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi Gagal',
                'errors' => $validator->errors()
            ], 400);
        }
    
        // Update kategori berita
        $kategori->name_kategori = $request->name_kategori;
        $kategori->save();
    
        return response()->json([
            'success' => true,
            'message' => 'Kategori Berita Berhasil Diperbarui',
            'data' => $kategori
        ], 200);
    }

    /**
     * Menghapus kategori berita berdasarkan ID
     */
    public function destroy($id)
    {
        $kategori = KategoriBerita::find($id);

        if (!$kategori) {
            return response()->json([
                'success' => false,
                'message' => 'Kategori tidak ditemukan'
            ], 404);
        }

        $kategori->delete();

        return response()->json([
            'success' => true,
            'message' => 'Kategori Berita Berhasil Dihapus'
        ], 200);
    }
}
