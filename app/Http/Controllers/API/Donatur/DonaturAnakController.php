<?php

namespace App\Http\Controllers\Api\Donatur;

use App\Http\Controllers\Controller;
use App\Models\Anak;
use App\Models\Donatur;
use Illuminate\Http\Request;

class DonaturAnakController extends Controller
{
    /**
     * Get sponsored children list for authenticated donatur
     */
    public function index(Request $request)
    {
        try {
            $donatur = Donatur::where('id_users', $request->user()->id_users)->first();
            
            if (!$donatur) {
                return response()->json([
                    'success' => false,
                    'message' => 'Donatur profile not found'
                ], 404);
            }

            $children = Anak::where('id_donatur', $donatur->id_donatur)
                ->with(['shelter', 'kelompok', 'keluarga'])
                ->aktif()
                ->get();

            return response()->json([
                'success' => true,
                'data' => $children
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve sponsored children',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get specific child details for authenticated donatur
     */
    public function show(Request $request, $id)
    {
        try {
            $donatur = Donatur::where('id_users', $request->user()->id_users)->first();
            
            if (!$donatur) {
                return response()->json([
                    'success' => false,
                    'message' => 'Donatur profile not found'
                ], 404);
            }

            $child = Anak::where('id_anak', $id)
                ->where('id_donatur', $donatur->id_donatur)
                ->with([
                    'shelter', 
                    'kelompok', 
                    'keluarga',
                    'anakPendidikan',
                    'levelAnakBinaan'
                ])
                ->first();

            if (!$child) {
                return response()->json([
                    'success' => false,
                    'message' => 'Child not found or not sponsored by you'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $child
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve child details',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}