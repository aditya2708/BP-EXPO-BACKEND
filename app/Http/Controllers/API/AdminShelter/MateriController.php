<?php

namespace App\Http\Controllers\API\AdminShelter;

use App\Http\Controllers\Controller;
use App\Models\Materi;
use Illuminate\Http\Request;

class MateriController extends Controller
{
    /**
     * Get materi by level
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function getByLevel(Request $request)
    {
        $levelId = $request->input('id_level_anak_binaan');
        
        if (!$levelId) {
            return response()->json([
                'success' => false,
                'message' => 'Level ID is required'
            ], 400);
        }
        
        $materi = Materi::where('id_level_anak_binaan', $levelId)
                        ->select('id_materi', 'mata_pelajaran', 'nama_materi')
                        ->orderBy('mata_pelajaran')
                        ->orderBy('nama_materi')
                        ->get();
        
        return response()->json([
            'success' => true,
            'data' => $materi
        ]);
    }
}