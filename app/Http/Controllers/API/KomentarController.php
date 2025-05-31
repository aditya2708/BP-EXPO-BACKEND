<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\KomentarBerita;
use App\Models\Berita;
use App\Http\Resources\Komentar\KomentarBeritaCollection;

class KomentarController extends Controller
{
    public function all(Request $request)
    {
        $query = KomentarBerita::with(['berita', 'replies.replies'])
            ->whereNull('parent_id') // hanya ambil komentar utama
            ->latest();
    
        // Filter berdasarkan nama pengirim
        if ($request->filled('search')) {
            $searchTerm = $request->search;
    
            $query->where(function ($q) use ($searchTerm) {
                $q->where('nama_pengirim', 'like', '%' . $searchTerm . '%')
                  ->orWhereHas('berita', function ($beritaQuery) use ($searchTerm) {
                      $beritaQuery->where('judul', 'like', '%' . $searchTerm . '%');
                  });
            });
        }
    
        // Paginate hasil query
        $komentar = $query->paginate($request->per_page ?? 10);
    
        return response()->json([
            'status' => true,
            'message' => 'Daftar seluruh komentar dari semua berita',
            'data' => \App\Http\Resources\Komentar\KomentarBeritaCollection::collection($komentar),
            'pagination' => [
                'total' => $komentar->total(),
                'per_page' => $komentar->perPage(),
                'current_page' => $komentar->currentPage(),
                'last_page' => $komentar->lastPage(),
                'from' => $komentar->firstItem(),
                'to' => $komentar->lastItem()
            ]
        ]);
    }

    
    /**
     * Menampilkan semua komentar utama dan balasannya untuk satu berita
     */
    public function index($id_berita)
    {
        $komentar = KomentarBerita::with('replies.replies.replies') // bisa ditambah jika perlu lebih dalam
            ->where('id_berita', $id_berita)
            ->whereNull('parent_id') // hanya komentar utama
            ->latest()
            ->get();

        return response()->json([
            'status' => true,
            'message' => 'Daftar komentar berita',
            'data' => KomentarBeritaCollection::collection($komentar)
        ]);
    }
    
    public function komentarByJudul($judul)
    {
        $berita = Berita::where('judul', urldecode($judul))->first();
    
        if (!$berita) {
            return response()->json([
                'status' => false,
                'message' => 'Berita tidak ditemukan',
            ], 404);
        }
    
        $komentar = KomentarBerita::with('replies.replies.replies')
            ->where('id_berita', $berita->id_berita) // ✅ FIX DI SINI
            ->whereNull('parent_id')
            ->latest()
            ->get();
    
        return response()->json([
            'status' => true,
            'message' => 'Komentar berdasarkan judul',
            'data' => KomentarBeritaCollection::collection($komentar)
        ]);
    }

    public function countBySingleBerita($id_berita)
    {
        $count = KomentarBerita::where('id_berita', $id_berita)->count();
        $judul = \App\Models\Berita::find($id_berita)?->judul ?? '-';
    
        return response()->json([
            'status' => true,
            'message' => 'Jumlah komentar untuk berita',
            'data' => [
                'id_berita' => $id_berita,
                'judul' => $judul,
                'jumlah_komentar' => $count
            ]
        ]);
    }



    /**
     * Menyimpan komentar baru (utama atau balasan)
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'id_berita'      => 'nullable|exists:berita,id_berita',
            'nama_pengirim'  => 'nullable|string|max:255',
            'isi_komentar'   => 'nullable|string',
            'parent_id'      => 'nullable|exists:komentar_berita,id_komentar',
        ]);

        $komentar = KomentarBerita::create($validated);

        return response()->json([
            'status' => true,
            'message' => 'Komentar berhasil ditambahkan',
            'data' => new KomentarBeritaCollection($komentar->load('replies'))
        ], 201);
    }
    
    public function destroy($id)
    {
        $komentar = KomentarBerita::find($id);
    
        if (!$komentar) {
            return response()->json([
                'status' => false,
                'message' => 'Komentar tidak ditemukan'
            ], 404);
        }
    
        $komentar->delete();
    
        return response()->json([
            'status' => true,
            'message' => 'Komentar berhasil dihapus'
        ]);
    }
    
    public function likeKomentar($id)
    {
        $komentar = KomentarBerita::find($id);
    
        if (!$komentar) {
            return response()->json([
                'success' => false,
                'message' => 'Komentar tidak ditemukan'
            ], 404);
        }
    
        // Toggle Like (pakai session key unik per komentar)
        if (session()->has("liked_komentar_$id")) {
            $komentar->decrement('likes_komentar');
            session()->forget("liked_komentar_$id");
    
            return response()->json([
                'success' => true,
                'message' => 'Komentar di-unlike',
                'likes' => $komentar->likes_komentar
            ]);
        }
    
        $komentar->increment('likes_komentar');
        session()->put("liked_komentar_$id", true);
    
        return response()->json([
            'success' => true,
            'message' => 'Komentar di-like',
            'likes' => $komentar->likes_komentar
        ]);
    }
    
    
    public function toggleStatusKomentar($id)
    {
        $komentar = KomentarBerita::find($id);
    
        if (!$komentar) {
            return response()->json([
                'success' => false,
                'message' => 'Komentar tidak ditemukan'
            ], 404);
        }
    
        $komentar->status_komentar = $komentar->status_komentar === KomentarBerita::STATUS_AKTIF
            ? KomentarBerita::STATUS_TIDAK_AKTIF
            : KomentarBerita::STATUS_AKTIF;
    
        $komentar->save();
    
        return response()->json([
            'success' => true,
            'message' => 'Status komentar berhasil diperbarui',
            'data' => [
                'id' => $komentar->id_komentar,
                'status_komentar' => $komentar->status_komentar
            ]
        ]);
    }


}
