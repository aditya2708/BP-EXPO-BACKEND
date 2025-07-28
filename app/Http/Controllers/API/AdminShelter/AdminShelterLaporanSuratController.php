<?php

namespace App\Http\Controllers\API\AdminShelter;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SuratAb;
use App\Models\Anak;
use App\Models\Shelter;
use Illuminate\Support\Facades\DB;

class AdminShelterLaporanSuratController extends Controller
{
    /**
     * Get laporan surat with filtering
     */
    public function getLaporanSurat(Request $request)
    {
        try {
            $user = auth()->user();
            $adminShelter = $user->adminShelter;
            $shelterId = $adminShelter->id_shelter;

            // Base query for surat in this shelter
            $query = SuratAb::whereHas('anak', function($q) use ($shelterId) {
                $q->where('id_shelter', $shelterId);
            });

            // Apply date filters
            if ($request->filled('start_date')) {
                $query->whereDate('tanggal', '>=', $request->start_date);
            }

            if ($request->filled('end_date')) {
                $query->whereDate('tanggal', '<=', $request->end_date);
            }

            if ($request->filled('is_read')) {
                $query->where('is_read', $request->boolean('is_read'));
            }

            // Get shelter info
            $shelter = Shelter::find($shelterId);

            // Calculate statistics
            $totalSurat = $query->count();
            $suratTerbaca = $query->where('is_read', 1)->count();
            $suratBelumTerbaca = $totalSurat - $suratTerbaca;

            $shelterStats = [[
                'id_shelter' => $shelterId,
                'nama_shelter' => $shelter->nama_shelter,
                'total_surat' => $totalSurat,
                'surat_terbaca' => $suratTerbaca,
                'surat_belum_terbaca' => $suratBelumTerbaca
            ]];

            $totalStats = [
                'total_shelter' => 1,
                'total_surat' => $totalSurat,
                'total_terbaca' => $suratTerbaca,
                'total_belum_terbaca' => $suratBelumTerbaca,
            ];

            return response()->json([
                'message' => 'Laporan surat retrieved successfully',
                'data' => [
                    'statistics' => $totalStats,
                    'shelter_stats' => $shelterStats
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error retrieving laporan surat',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get detail surat for specific shelter
     */
    public function getShelterDetail(Request $request, $shelterId)
    {
        try {
            $user = auth()->user();
            $adminShelter = $user->adminShelter;

            // Verify access - admin can only access their own shelter
            if ($adminShelter->id_shelter != $shelterId) {
                return response()->json(['message' => 'Unauthorized access'], 403);
            }

            $query = SuratAb::with(['anak' => function($q) {
                    $q->select('id_anak', 'full_name', 'nick_name', 'foto');
                }])
                ->whereHas('anak', function($q) use ($shelterId) {
                    $q->where('id_shelter', $shelterId);
                });

            // Apply filters
            if ($request->filled('start_date')) {
                $query->whereDate('tanggal', '>=', $request->start_date);
            }

            if ($request->filled('end_date')) {
                $query->whereDate('tanggal', '<=', $request->end_date);
            }

            if ($request->filled('is_read')) {
                $query->where('is_read', $request->boolean('is_read'));
            }

            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('pesan', 'like', "%{$search}%")
                      ->orWhereHas('anak', function($subQ) use ($search) {
                          $subQ->where('full_name', 'like', "%{$search}%")
                               ->orWhere('nick_name', 'like', "%{$search}%");
                      });
                });
            }

            $perPage = $request->get('per_page', 15);
            $surat = $query->orderBy('tanggal', 'desc')
                          ->paginate($perPage);

            // Transform data
            $surat->getCollection()->transform(function($item) {
                return [
                    'id_surat' => $item->id_surat,
                    'pesan' => $item->pesan,
                    'foto' => $item->foto,
                    'tanggal' => $item->tanggal,
                    'is_read' => $item->is_read,
                    'anak' => [
                        'id_anak' => $item->anak->id_anak,
                        'full_name' => $item->anak->full_name,
                        'nick_name' => $item->anak->nick_name,
                        'foto_url' => $item->anak->foto_url,
                    ]
                ];
            });

            return response()->json([
                'message' => 'Shelter surat detail retrieved successfully',
                'data' => $surat
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error retrieving shelter detail',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get filter options for dropdown
     */
    public function getFilterOptions()
    {
        try {
            $user = auth()->user();
            $adminShelter = $user->adminShelter;
            $shelterId = $adminShelter->id_shelter;

            // For admin shelter, only return their own shelter
            $shelter = Shelter::where('id_shelter', $shelterId)
                ->select('id_shelter', 'nama_shelter')
                ->first();

            $options = [
                'shelter' => $shelter ? [$shelter] : []
            ];

            return response()->json([
                'message' => 'Filter options retrieved successfully',
                'data' => $options
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error retrieving filter options',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get available years for filter
     */
    public function getAvailableYears()
    {
        try {
            $user = auth()->user();
            $adminShelter = $user->adminShelter;
            $shelterId = $adminShelter->id_shelter;

            $years = SuratAb::whereHas('anak', function($q) use ($shelterId) {
                    $q->where('id_shelter', $shelterId);
                })
                ->selectRaw('DISTINCT YEAR(tanggal) as year')
                ->orderBy('year', 'desc')
                ->pluck('year');

            return response()->json([
                'message' => 'Available years retrieved successfully',
                'data' => $years
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error retrieving available years',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}