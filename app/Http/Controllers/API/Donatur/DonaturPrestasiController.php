<?php

namespace App\Http\Controllers\Api\Donatur;

use App\Http\Controllers\Controller;
use App\Models\Prestasi;
use App\Models\Anak;
use App\Models\Donatur;
use Illuminate\Http\Request;

class DonaturPrestasiController extends Controller
{
    public function index(Request $request, $childId)
    {
        try {
            $donatur = Donatur::where('id_users', $request->user()->id_users)->first();
            
            if (!$donatur) {
                return response()->json([
                    'success' => false,
                    'message' => 'Donatur profile not found'
                ], 404);
            }

            $child = Anak::where('id_anak', $childId)
                ->where('id_donatur', $donatur->id_donatur)
                ->first();

            if (!$child) {
                return response()->json([
                    'success' => false,
                    'message' => 'Child not found or not sponsored by you'
                ], 404);
            }

            $prestasiList = Prestasi::where('id_anak', $childId)
                ->with('anak')
                ->orderBy('tgl_upload', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $prestasiList
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve achievements',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show(Request $request, $childId, $prestasiId)
    {
        try {
            $donatur = Donatur::where('id_users', $request->user()->id_users)->first();
            
            if (!$donatur) {
                return response()->json([
                    'success' => false,
                    'message' => 'Donatur profile not found'
                ], 404);
            }

            $child = Anak::where('id_anak', $childId)
                ->where('id_donatur', $donatur->id_donatur)
                ->first();

            if (!$child) {
                return response()->json([
                    'success' => false,
                    'message' => 'Child not found or not sponsored by you'
                ], 404);
            }

            $prestasi = Prestasi::where('id_prestasi', $prestasiId)
                ->where('id_anak', $childId)
                ->with('anak')
                ->first();

            if (!$prestasi) {
                return response()->json([
                    'success' => false,
                    'message' => 'Achievement not found'
                ], 404);
            }

            if (!$prestasi->is_read) {
                $prestasi->update(['is_read' => true]);
            }

            return response()->json([
                'success' => true,
                'data' => $prestasi
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve achievement details',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function markAsRead(Request $request, $childId, $prestasiId)
    {
        try {
            $donatur = Donatur::where('id_users', $request->user()->id_users)->first();
            
            if (!$donatur) {
                return response()->json([
                    'success' => false,
                    'message' => 'Donatur profile not found'
                ], 404);
            }

            $child = Anak::where('id_anak', $childId)
                ->where('id_donatur', $donatur->id_donatur)
                ->first();

            if (!$child) {
                return response()->json([
                    'success' => false,
                    'message' => 'Child not found or not sponsored by you'
                ], 404);
            }

            $prestasi = Prestasi::where('id_prestasi', $prestasiId)
                ->where('id_anak', $childId)
                ->first();

            if (!$prestasi) {
                return response()->json([
                    'success' => false,
                    'message' => 'Achievement not found'
                ], 404);
            }

            $prestasi->update(['is_read' => true]);

            return response()->json([
                'success' => true,
                'message' => 'Achievement marked as read'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to mark achievement as read',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}