<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Absen;
use App\Models\AbsenUser;
use App\Models\Aktivitas;
use App\Models\Anak;
use App\Models\Kelompok;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Exception;

class AbsenApiController extends Controller
{
    /**
     * Get attendance records for a specific activity
     *
     * @param int $id_aktivitas
     * @return \Illuminate\Http\JsonResponse
     */
    public function getByAktivitas($id_aktivitas)
    {
        try {
            // Get the aktivitas record
            $aktivitas = Aktivitas::with('shelter')->find($id_aktivitas);
            
            if (!$aktivitas) {
                return response()->json([
                    'success' => false,
                    'message' => 'Aktivitas tidak ditemukan'
                ], 404);
            }
            
            // Check if current user has permission to access this aktivitas
            $user = auth()->user();
            $adminShelter = $user->adminShelter;
            
            if (!$adminShelter || $adminShelter->id_shelter != $aktivitas->id_shelter) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda tidak memiliki akses ke aktivitas ini'
                ], 403);
            }
            
            // Get the available children based on aktivitas type
            $absenData = [];
            
            if ($aktivitas->jenis_kegiatan === 'Bimbel' && $aktivitas->nama_kelompok) {
                // For Bimbel type, get children from the related kelompok
                $kelompok = Kelompok::where('nama_kelompok', $aktivitas->nama_kelompok)
                                    ->where('id_shelter', $aktivitas->id_shelter)
                                    ->first();
                
                if ($kelompok) {
                    $anak = Anak::where('id_kelompok', $kelompok->id_kelompok)
                                ->where('status_validasi', 'aktif')
                                ->get();
                                
                    foreach ($anak as $a) {
                        $absen = $this->getAbsenStatus($aktivitas->id_aktivitas, $a->id_anak);
                        
                        $absenData[] = [
                            'id_anak' => $a->id_anak,
                            'nama_anak' => $a->full_name,
                            'nick_name' => $a->nick_name,
                            'foto_url' => $a->foto_url,
                            'status_absen' => $absen
                        ];
                    }
                }
            } else {
                // For other types, get children from the same shelter
                $anak = Anak::where('id_shelter', $aktivitas->id_shelter)
                            ->where('status_validasi', 'aktif')
                            ->get();
                
                foreach ($anak as $a) {
                    $absen = $this->getAbsenStatus($aktivitas->id_aktivitas, $a->id_anak);
                    
                    $absenData[] = [
                        'id_anak' => $a->id_anak,
                        'nama_anak' => $a->full_name,
                        'nick_name' => $a->nick_name,
                        'foto_url' => $a->foto_url,
                        'status_absen' => $absen
                    ];
                }
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Data absensi berhasil diambil',
                'data' => $absenData,
                'aktivitas' => $aktivitas
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data absensi: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get attendance status for a child in an activity
     * 
     * @param int $id_aktivitas
     * @param int $id_anak
     * @return string
     */
    private function getAbsenStatus($id_aktivitas, $id_anak)
    {
        // Find AbsenUser record for the child
        $absenUser = AbsenUser::where('id_anak', $id_anak)->first();
        
        if (!$absenUser) {
            return 'Tidak'; // Default to absent if no record found
        }
        
        // Find Absen record for this activity and absen_user
        $absen = Absen::where('id_aktivitas', $id_aktivitas)
                       ->where('id_absen_user', $absenUser->id_absen_user)
                       ->first();
        
        if (!$absen) {
            return 'Tidak'; // Default to absent if no record found
        }
        
        return $absen->absen; // Return the actual status
    }
    
    /**
     * Submit attendance data for an activity
     * 
     * @param Request $request
     * @param int $id_aktivitas
     * @return \Illuminate\Http\JsonResponse
     */
    public function submitAbsen(Request $request, $id_aktivitas)
    {
        try {
            // Validate input
            $validator = Validator::make($request->all(), [
                'absen' => 'required|array',
                'absen.*.id_anak' => 'required|exists:anak,id_anak',
                'absen.*.status_absen' => 'required|in:Ya,Tidak,Izin'
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            // Check if the activity exists
            $aktivitas = Aktivitas::find($id_aktivitas);
            
            if (!$aktivitas) {
                return response()->json([
                    'success' => false,
                    'message' => 'Aktivitas tidak ditemukan'
                ], 404);
            }
            
            // Check if current user has permission to access this aktivitas
            $user = auth()->user();
            $adminShelter = $user->adminShelter;
            
            if (!$adminShelter || $adminShelter->id_shelter != $aktivitas->id_shelter) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda tidak memiliki akses ke aktivitas ini'
                ], 403);
            }
            
            // Process attendance data
            DB::beginTransaction();
            
            $absenData = $request->input('absen');
            
            foreach ($absenData as $data) {
                $id_anak = $data['id_anak'];
                $status_absen = $data['status_absen'];
                
                // Get or create AbsenUser record
                $absenUser = AbsenUser::firstOrCreate(['id_anak' => $id_anak]);
                
                // Get or create Absen record
                $absen = Absen::updateOrCreate(
                    [
                        'id_aktivitas' => $id_aktivitas,
                        'id_absen_user' => $absenUser->id_absen_user
                    ],
                    [
                        'absen' => $status_absen,
                        'is_read' => false // Mark as unread
                    ]
                );
            }
            
            DB::commit();
            
            // Get updated data
            $updatedData = $this->getUpdatedAbsenData($id_aktivitas);
            
            return response()->json([
                'success' => true,
                'message' => 'Data absensi berhasil disimpan',
                'data' => $updatedData
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan data absensi: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Update attendance status for a child in an activity
     * 
     * @param Request $request
     * @param int $id_aktivitas
     * @param int $id_anak
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateStatus(Request $request, $id_aktivitas, $id_anak)
    {
        try {
            // Validate input
            $validator = Validator::make($request->all(), [
                'status_absen' => 'required|in:Ya,Tidak,Izin'
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            // Check if the activity exists
            $aktivitas = Aktivitas::find($id_aktivitas);
            
            if (!$aktivitas) {
                return response()->json([
                    'success' => false,
                    'message' => 'Aktivitas tidak ditemukan'
                ], 404);
            }
            
            // Check if current user has permission to access this aktivitas
            $user = auth()->user();
            $adminShelter = $user->adminShelter;
            
            if (!$adminShelter || $adminShelter->id_shelter != $aktivitas->id_shelter) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda tidak memiliki akses ke aktivitas ini'
                ], 403);
            }
            
            // Check if the child exists
            $anak = Anak::find($id_anak);
            
            if (!$anak) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anak tidak ditemukan'
                ], 404);
            }
            
            // Process status update
            DB::beginTransaction();
            
            $status_absen = $request->input('status_absen');
            
            // Get or create AbsenUser record
            $absenUser = AbsenUser::firstOrCreate(['id_anak' => $id_anak]);
            
            // Get or create Absen record
            $absen = Absen::updateOrCreate(
                [
                    'id_aktivitas' => $id_aktivitas,
                    'id_absen_user' => $absenUser->id_absen_user
                ],
                [
                    'absen' => $status_absen,
                    'is_read' => false // Mark as unread
                ]
            );
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Status absensi berhasil diperbarui',
                'data' => [
                    'id_anak' => $id_anak,
                    'status_absen' => $status_absen
                ]
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui status absensi: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get available children for an activity
     * 
     * @param int $id_aktivitas
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAvailableChildren($id_aktivitas)
    {
        try {
            // Get the aktivitas record
            $aktivitas = Aktivitas::find($id_aktivitas);
            
            if (!$aktivitas) {
                return response()->json([
                    'success' => false,
                    'message' => 'Aktivitas tidak ditemukan'
                ], 404);
            }
            
            // Check if current user has permission to access this aktivitas
            $user = auth()->user();
            $adminShelter = $user->adminShelter;
            
            if (!$adminShelter || $adminShelter->id_shelter != $aktivitas->id_shelter) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda tidak memiliki akses ke aktivitas ini'
                ], 403);
            }
            
            // Get children based on aktivitas type
            $availableChildren = [];
            
            if ($aktivitas->jenis_kegiatan === 'Bimbel' && $aktivitas->nama_kelompok) {
                // For Bimbel type, get children from the related kelompok
                $kelompok = Kelompok::where('nama_kelompok', $aktivitas->nama_kelompok)
                                    ->where('id_shelter', $aktivitas->id_shelter)
                                    ->first();
                
                if ($kelompok) {
                    $availableChildren = Anak::where('id_kelompok', $kelompok->id_kelompok)
                                            ->where('status_validasi', 'aktif')
                                            ->get();
                }
            } else {
                // For other types, get children from the same shelter
                $availableChildren = Anak::where('id_shelter', $aktivitas->id_shelter)
                                         ->where('status_validasi', 'aktif')
                                         ->get();
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Data anak berhasil diambil',
                'data' => $availableChildren
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data anak: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get updated attendance data after an update
     * 
     * @param int $id_aktivitas
     * @return array
     */
    private function getUpdatedAbsenData($id_aktivitas)
    {
        $aktivitas = Aktivitas::find($id_aktivitas);
        $absenData = [];
        
        if ($aktivitas->jenis_kegiatan === 'Bimbel' && $aktivitas->nama_kelompok) {
            // For Bimbel type, get children from the related kelompok
            $kelompok = Kelompok::where('nama_kelompok', $aktivitas->nama_kelompok)
                                ->where('id_shelter', $aktivitas->id_shelter)
                                ->first();
            
            if ($kelompok) {
                $anak = Anak::where('id_kelompok', $kelompok->id_kelompok)
                            ->where('status_validasi', 'aktif')
                            ->get();
                            
                foreach ($anak as $a) {
                    $absen = $this->getAbsenStatus($aktivitas->id_aktivitas, $a->id_anak);
                    
                    $absenData[] = [
                        'id_anak' => $a->id_anak,
                        'nama_anak' => $a->full_name,
                        'nick_name' => $a->nick_name,
                        'foto_url' => $a->foto_url,
                        'status_absen' => $absen
                    ];
                }
            }
        } else {
            // For other types, get children from the same shelter
            $anak = Anak::where('id_shelter', $aktivitas->id_shelter)
                        ->where('status_validasi', 'aktif')
                        ->get();
            
            foreach ($anak as $a) {
                $absen = $this->getAbsenStatus($aktivitas->id_aktivitas, $a->id_anak);
                
                $absenData[] = [
                    'id_anak' => $a->id_anak,
                    'nama_anak' => $a->full_name,
                    'nick_name' => $a->nick_name,
                    'foto_url' => $a->foto_url,
                    'status_absen' => $absen
                ];
            }
        }
        
        return $absenData;
    }
}