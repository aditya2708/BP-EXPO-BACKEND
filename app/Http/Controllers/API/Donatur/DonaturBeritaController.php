<?php

namespace App\Http\Controllers\API\Donatur;

use App\Http\Controllers\Controller;
use App\Http\Resources\BeritaResource;
use App\Models\Berita;
use Illuminate\Http\Request;

class DonaturBeritaController extends Controller
{
    public function index(Request $request)
    {
        $query = Berita::with(['kategori', 'tags'])
            ->where('status_berita', 'Aktif')
            ->orderBy('tanggal', 'desc');

        if ($request->has('kategori') && $request->kategori) {
            $query->where('id_kategori_berita', $request->kategori);
        }

        if ($request->has('search') && $request->search) {
            $query->where(function($q) use ($request) {
                $q->where('judul', 'like', '%' . $request->search . '%')
                  ->orWhere('konten', 'like', '%' . $request->search . '%');
            });
        }

        $perPage = $request->get('per_page', 10);
        $berita = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Berita retrieved successfully',
            'data' => BeritaResource::collection($berita->items()),
            'pagination' => [
                'current_page' => $berita->currentPage(),
                'per_page' => $berita->perPage(),
                'total' => $berita->total(),
                'last_page' => $berita->lastPage(),
                'has_more_pages' => $berita->hasMorePages()
            ]
        ]);
    }

    public function show($id)
    {
        $berita = Berita::with(['kategori', 'tags', 'komentar'])
            ->where('status_berita', 'Aktif')
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'message' => 'Berita detail retrieved successfully',
            'data' => new BeritaResource($berita)
        ]);
    }

    public function incrementView($id)
    {
        $berita = Berita::where('status_berita', 'Aktif')->findOrFail($id);
        $berita->increment('views_berita');

        return response()->json([
            'success' => true,
            'message' => 'View count incremented successfully',
            'data' => [
                'views_berita' => $berita->fresh()->views_berita
            ]
        ]);
    }
}