<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Kegiatan;

class KegiatanApiController extends Controller
{
    /**
     * Display a listing of kegiatan.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        try {
            $kegiatan = Kegiatan::all();
            
            return response()->json([
                'success' => true,
                'message' => 'Data kegiatan berhasil diambil',
                'data' => $kegiatan
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data kegiatan: ' . $e->getMessage()
            ], 500);
        }
    }
}