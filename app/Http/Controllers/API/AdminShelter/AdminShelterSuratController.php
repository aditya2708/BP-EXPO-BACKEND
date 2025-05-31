<?php

namespace App\Http\Controllers\API\AdminShelter;

use App\Http\Controllers\Controller;
use App\Models\SuratAb;
use App\Models\Anak;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class AdminShelterSuratController extends Controller
{
    /**
     * Get surat list for specific child
     */
    public function index(Request $request, $childId)
    {
        try {
            $user = Auth::user();
            
            if (!$user->adminShelter) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access'
                ], 403);
            }

            // Verify child belongs to this shelter
            $child = Anak::where('id_anak', $childId)
                ->where('id_shelter', $user->adminShelter->id_shelter)
                ->first();

            if (!$child) {
                return response()->json([
                    'success' => false,
                    'message' => 'Child not found in your shelter'
                ], 404);
            }

            $suratList = SuratAb::where('id_anak', $childId)
                ->with('anak')
                ->orderBy('tanggal', 'desc')
                ->get()
                ->map(function ($surat) {
                    return [
                        'id_surat' => $surat->id_surat,
                        'id_anak' => $surat->id_anak,
                        'pesan' => $surat->pesan,
                        'foto' => $surat->foto,
                        'foto_url' => $surat->foto ? asset("storage/SuratAb/{$surat->id_anak}/{$surat->foto}") : null,
                        'tanggal' => $surat->tanggal,
                        'is_read' => $surat->is_read,
                        'anak' => $surat->anak ? [
                            'id_anak' => $surat->anak->id_anak,
                            'full_name' => $surat->anak->full_name,
                            'nick_name' => $surat->anak->nick_name
                        ] : null
                    ];
                });

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
            $user = Auth::user();
            
            if (!$user->adminShelter) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access'
                ], 403);
            }

            // Verify child belongs to this shelter
            $child = Anak::where('id_anak', $childId)
                ->where('id_shelter', $user->adminShelter->id_shelter)
                ->first();

            if (!$child) {
                return response()->json([
                    'success' => false,
                    'message' => 'Child not found in your shelter'
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

            $suratData = [
                'id_surat' => $surat->id_surat,
                'id_anak' => $surat->id_anak,
                'pesan' => $surat->pesan,
                'foto' => $surat->foto,
                'foto_url' => $surat->foto ? asset("storage/SuratAb/{$surat->id_anak}/{$surat->foto}") : null,
                'tanggal' => $surat->tanggal,
                'is_read' => $surat->is_read,
                'anak' => $surat->anak ? [
                    'id_anak' => $surat->anak->id_anak,
                    'full_name' => $surat->anak->full_name,
                    'nick_name' => $surat->anak->nick_name
                ] : null
            ];

            return response()->json([
                'success' => true,
                'data' => $suratData
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
     * Create new surat from admin shelter
     */
    public function store(Request $request, $childId)
    {
        try {
            $user = Auth::user();
            
            if (!$user->adminShelter) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access'
                ], 403);
            }

            // Verify child belongs to this shelter
            $child = Anak::where('id_anak', $childId)
                ->where('id_shelter', $user->adminShelter->id_shelter)
                ->first();

            if (!$child) {
                return response()->json([
                    'success' => false,
                    'message' => 'Child not found in your shelter'
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

            $responseData = [
                'id_surat' => $surat->id_surat,
                'id_anak' => $surat->id_anak,
                'pesan' => $surat->pesan,
                'foto' => $surat->foto,
                'foto_url' => $surat->foto ? asset("storage/SuratAb/{$surat->id_anak}/{$surat->foto}") : null,
                'tanggal' => $surat->tanggal,
                'is_read' => $surat->is_read,
                'anak' => $surat->anak ? [
                    'id_anak' => $surat->anak->id_anak,
                    'full_name' => $surat->anak->full_name,
                    'nick_name' => $surat->anak->nick_name
                ] : null
            ];

            return response()->json([
                'success' => true,
                'message' => 'Message sent successfully',
                'data' => $responseData
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
     * Update existing surat
     */
    public function update(Request $request, $childId, $suratId)
    {
        try {
            $user = Auth::user();
            
            if (!$user->adminShelter) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access'
                ], 403);
            }

            // Verify child belongs to this shelter
            $child = Anak::where('id_anak', $childId)
                ->where('id_shelter', $user->adminShelter->id_shelter)
                ->first();

            if (!$child) {
                return response()->json([
                    'success' => false,
                    'message' => 'Child not found in your shelter'
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

            $validator = Validator::make($request->all(), [
                'pesan' => 'sometimes|string|max:1000',
                'foto' => 'nullable|image|mimes:jpeg,png,jpg|max:2048'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            if ($request->has('pesan')) {
                $surat->pesan = $request->pesan;
            }

            // Handle photo upload
            if ($request->hasFile('foto')) {
                // Delete old photo if exists
                if ($surat->foto) {
                    Storage::disk('public')->delete("SuratAb/{$childId}/{$surat->foto}");
                }

                $foto = $request->file('foto');
                $fileName = time() . '_' . $foto->getClientOriginalName();
                $path = "SuratAb/{$childId}";
                
                $foto->storeAs($path, $fileName, 'public');
                $surat->foto = $fileName;
            }

            $surat->save();

            $responseData = [
                'id_surat' => $surat->id_surat,
                'id_anak' => $surat->id_anak,
                'pesan' => $surat->pesan,
                'foto' => $surat->foto,
                'foto_url' => $surat->foto ? asset("storage/SuratAb/{$surat->id_anak}/{$surat->foto}") : null,
                'tanggal' => $surat->tanggal,
                'is_read' => $surat->is_read,
                'anak' => $surat->anak ? [
                    'id_anak' => $surat->anak->id_anak,
                    'full_name' => $surat->anak->full_name,
                    'nick_name' => $surat->anak->nick_name
                ] : null
            ];

            return response()->json([
                'success' => true,
                'message' => 'Message updated successfully',
                'data' => $responseData
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update message',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete surat
     */
    public function destroy(Request $request, $childId, $suratId)
    {
        try {
            $user = Auth::user();
            
            if (!$user->adminShelter) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access'
                ], 403);
            }

            // Verify child belongs to this shelter
            $child = Anak::where('id_anak', $childId)
                ->where('id_shelter', $user->adminShelter->id_shelter)
                ->first();

            if (!$child) {
                return response()->json([
                    'success' => false,
                    'message' => 'Child not found in your shelter'
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

            // Delete photo if exists
            if ($surat->foto) {
                Storage::disk('public')->delete("SuratAb/{$childId}/{$surat->foto}");
            }

            $surat->delete();

            return response()->json([
                'success' => true,
                'message' => 'Message deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete message',
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
            $user = Auth::user();
            
            if (!$user->adminShelter) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access'
                ], 403);
            }

            // Verify child belongs to this shelter
            $child = Anak::where('id_anak', $childId)
                ->where('id_shelter', $user->adminShelter->id_shelter)
                ->first();

            if (!$child) {
                return response()->json([
                    'success' => false,
                    'message' => 'Child not found in your shelter'
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