<?php

namespace App\Http\Controllers\Api\Donatur;

use App\Http\Controllers\Controller;
use App\Models\SuratAb;
use App\Models\Anak;
use App\Models\Donatur;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class DonaturSuratController extends Controller
{
    /**
     * Get surat list for specific child
     */
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

            // Verify child belongs to this donatur
            $child = Anak::where('id_anak', $childId)
                ->where('id_donatur', $donatur->id_donatur)
                ->first();

            if (!$child) {
                return response()->json([
                    'success' => false,
                    'message' => 'Child not found or not sponsored by you'
                ], 404);
            }

            $suratList = SuratAb::where('id_anak', $childId)
                ->with('anak')
                ->orderBy('tanggal', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $suratList
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve messages',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get specific surat details
     */
    public function show(Request $request, $childId, $suratId)
    {
        try {
            $donatur = Donatur::where('id_users', $request->user()->id_users)->first();
            
            if (!$donatur) {
                return response()->json([
                    'success' => false,
                    'message' => 'Donatur profile not found'
                ], 404);
            }

            // Verify child belongs to this donatur
            $child = Anak::where('id_anak', $childId)
                ->where('id_donatur', $donatur->id_donatur)
                ->first();

            if (!$child) {
                return response()->json([
                    'success' => false,
                    'message' => 'Child not found or not sponsored by you'
                ], 404);
            }

            $surat = SuratAb::where('id_surat', $suratId)
                ->where('id_anak', $childId)
                ->with('anak')
                ->first();

            if (!$surat) {
                return response()->json([
                    'success' => false,
                    'message' => 'Message not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $surat
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve message details',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create new surat from donatur
     */
    public function store(Request $request, $childId)
    {
        try {
            $donatur = Donatur::where('id_users', $request->user()->id_users)->first();
            
            if (!$donatur) {
                return response()->json([
                    'success' => false,
                    'message' => 'Donatur profile not found'
                ], 404);
            }

            // Verify child belongs to this donatur
            $child = Anak::where('id_anak', $childId)
                ->where('id_donatur', $donatur->id_donatur)
                ->first();

            if (!$child) {
                return response()->json([
                    'success' => false,
                    'message' => 'Child not found or not sponsored by you'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'pesan' => 'required|string|max:1000',
                'foto' => 'nullable|image|mimes:jpeg,png,jpg|max:2048'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $suratData = [
                'id_anak' => $childId,
                'pesan' => $request->pesan,
                'tanggal' => now(),
                'is_read' => false
            ];

            // Handle photo upload
            if ($request->hasFile('foto')) {
                $foto = $request->file('foto');
                $fileName = time() . '_' . $foto->getClientOriginalName();
                $path = "SuratAb/{$childId}";
                
                $foto->storeAs($path, $fileName, 'public');
                $suratData['foto'] = $fileName;
            }

            $surat = SuratAb::create($suratData);

            return response()->json([
                'success' => true,
                'message' => 'Message sent successfully',
                'data' => $surat->load('anak')
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send message',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mark surat as read
     */
    public function markAsRead(Request $request, $childId, $suratId)
    {
        try {
            $donatur = Donatur::where('id_users', $request->user()->id_users)->first();
            
            if (!$donatur) {
                return response()->json([
                    'success' => false,
                    'message' => 'Donatur profile not found'
                ], 404);
            }

            // Verify child belongs to this donatur
            $child = Anak::where('id_anak', $childId)
                ->where('id_donatur', $donatur->id_donatur)
                ->first();

            if (!$child) {
                return response()->json([
                    'success' => false,
                    'message' => 'Child not found or not sponsored by you'
                ], 404);
            }

            $surat = SuratAb::where('id_surat', $suratId)
                ->where('id_anak', $childId)
                ->first();

            if (!$surat) {
                return response()->json([
                    'success' => false,
                    'message' => 'Message not found'
                ], 404);
            }

            $surat->update(['is_read' => true]);

            return response()->json([
                'success' => true,
                'message' => 'Message marked as read'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to mark message as read',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}