<?php

namespace App\Http\Controllers\API\AdminShelter;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Histori;
use App\Models\Anak;
use Carbon\Carbon;

class AdminShelterLaporanHistoriController extends Controller
{
    public function getLaporanHistori(Request $request)
    {
        $shelterId = auth()->user()->adminShelter->id_shelter;
        
        // Date range filters (default to current year if not provided)
        $startDate = $request->get('start_date', date('Y-01-01'));
        $endDate = $request->get('end_date', date('Y-12-31'));
        $jenisHistori = $request->get('jenis_histori', null);
        $search = $request->get('search');
        $perPage = $request->get('per_page', 15);

        // Validate and parse dates
        try {
            $startDate = Carbon::parse($startDate)->startOfDay();
            $endDate = Carbon::parse($endDate)->endOfDay();
        } catch (\Exception $e) {
            return response()->json(['message' => 'Invalid date format'], 400);
        }

        // Base query untuk histori dari anak-anak di shelter ini
        $historiQuery = Histori::whereHas('anak', function($query) use ($shelterId) {
            $query->where('id_shelter', $shelterId)
                  ->whereIn('status_validasi', Anak::STATUS_AKTIF);
        })
        ->with(['anak'])
        ->whereBetween('tanggal', [$startDate, $endDate]);

        // Apply filters
        if ($jenisHistori) {
            $historiQuery->where('jenis_histori', $jenisHistori);
        }

        if ($search) {
            $historiQuery->whereHas('anak', function($query) use ($search) {
                $query->where('full_name', 'like', "%{$search}%")
                      ->orWhere('nick_name', 'like', "%{$search}%");
            });
        }

        $historiPaginated = $historiQuery->orderBy('tanggal', 'desc')->paginate($perPage);

        // Prepare report data
        $reportData = [];
        foreach ($historiPaginated as $histori) {
            $reportData[] = [
                'id_histori' => $histori->id_histori,
                'id_anak' => $histori->id_anak,
                'anak' => [
                    'id_anak' => $histori->anak->id_anak,
                    'full_name' => $histori->anak->full_name,
                    'nick_name' => $histori->anak->nick_name,
                    'foto_url' => $histori->anak->foto_url,
                    'jenis_kelamin' => $histori->anak->jenis_kelamin,
                    'umur' => $histori->anak->umur
                ],
                'jenis_histori' => $histori->jenis_histori,
                'nama_histori' => $histori->nama_histori,
                'tanggal' => $histori->tanggal->format('Y-m-d'),
                'tanggal_formatted' => $histori->tanggal->format('d M Y'),
                'di_opname' => $histori->di_opname,
                'foto_url' => $histori->foto ? url("storage/Histori/{$histori->id_histori}/{$histori->foto}") : null,
                'is_read' => $histori->is_read
            ];
        }

        // Calculate summary
        $summary = $this->calculateSummary($shelterId, $startDate, $endDate, $jenisHistori);

        // Get filter options
        $filterOptions = $this->getFilterOptions($shelterId);

        return response()->json([
            'message' => 'Laporan histori anak retrieved successfully',
            'data' => [
                'histori_list' => $reportData,
                'summary' => $summary,
                'pagination' => [
                    'current_page' => $historiPaginated->currentPage(),
                    'last_page' => $historiPaginated->lastPage(),
                    'per_page' => $historiPaginated->perPage(),
                    'total' => $historiPaginated->total()
                ],
                'filter_options' => $filterOptions
            ]
        ]);
    }

    public function getHistoriDetail(Request $request, $historiId)
    {
        $shelterId = auth()->user()->adminShelter->id_shelter;

        $histori = Histori::whereHas('anak', function($query) use ($shelterId) {
            $query->where('id_shelter', $shelterId);
        })
        ->with(['anak'])
        ->where('id_histori', $historiId)
        ->first();

        if (!$histori) {
            return response()->json(['message' => 'Histori not found or not accessible'], 404);
        }

        // Get other histori for this child for context
        $otherHistori = Histori::where('id_anak', $histori->id_anak)
            ->where('id_histori', '!=', $historiId)
            ->orderBy('tanggal', 'desc')
            ->limit(5)
            ->get()
            ->map(function($h) {
                return [
                    'id_histori' => $h->id_histori,
                    'jenis_histori' => $h->jenis_histori,
                    'nama_histori' => $h->nama_histori,
                    'tanggal' => $h->tanggal->format('Y-m-d'),
                    'tanggal_formatted' => $h->tanggal->format('d M Y')
                ];
            });

        $historiDetail = [
            'id_histori' => $histori->id_histori,
            'anak' => [
                'id_anak' => $histori->anak->id_anak,
                'full_name' => $histori->anak->full_name,
                'nick_name' => $histori->anak->nick_name,
                'foto_url' => $histori->anak->foto_url,
                'jenis_kelamin' => $histori->anak->jenis_kelamin,
                'umur' => $histori->anak->umur,
                'tanggal_lahir' => $histori->anak->tanggal_lahir
            ],
            'jenis_histori' => $histori->jenis_histori,
            'nama_histori' => $histori->nama_histori,
            'tanggal' => $histori->tanggal->format('Y-m-d'),
            'tanggal_formatted' => $histori->tanggal->format('d M Y'),
            'di_opname' => $histori->di_opname,
            'dirawat_id' => $histori->dirawat_id,
            'foto_url' => $histori->foto ? url("storage/Histori/{$histori->id_histori}/{$histori->foto}") : null,
            'is_read' => $histori->is_read,
            'created_at' => $histori->created_at,
            'updated_at' => $histori->updated_at,
            'other_histori' => $otherHistori
        ];

        // Mark as read if not already
        if (!$histori->is_read) {
            $histori->update(['is_read' => true]);
        }

        return response()->json([
            'message' => 'Histori detail retrieved successfully',
            'data' => $historiDetail
        ]);
    }

    public function getJenisHistoriOptions(Request $request)
    {
        $shelterId = auth()->user()->adminShelter->id_shelter;

        $jenisHistori = Histori::whereHas('anak', function($query) use ($shelterId) {
            $query->where('id_shelter', $shelterId);
        })
        ->select('jenis_histori')
        ->distinct()
        ->whereNotNull('jenis_histori')
        ->orderBy('jenis_histori')
        ->pluck('jenis_histori')
        ->toArray();

        return response()->json([
            'message' => 'Jenis histori options retrieved successfully',
            'data' => $jenisHistori
        ]);
    }

    public function getAvailableYears(Request $request)
    {
        $shelterId = auth()->user()->adminShelter->id_shelter;

        $years = Histori::whereHas('anak', function($query) use ($shelterId) {
            $query->where('id_shelter', $shelterId);
        })
        ->selectRaw('YEAR(tanggal) as year')
        ->distinct()
        ->orderBy('year', 'desc')
        ->pluck('year')
        ->toArray();

        return response()->json([
            'message' => 'Available years retrieved successfully',
            'data' => $years
        ]);
    }

    private function calculateSummary($shelterId, $startDate, $endDate, $jenisHistori = null)
    {
        // Base query for summary calculation
        $summaryQuery = Histori::whereHas('anak', function($query) use ($shelterId) {
            $query->where('id_shelter', $shelterId)
                  ->whereIn('status_validasi', Anak::STATUS_AKTIF);
        })
        ->whereBetween('tanggal', [$startDate, $endDate]);

        if ($jenisHistori) {
            $summaryQuery->where('jenis_histori', $jenisHistori);
        }

        $allHistori = $summaryQuery->get();
        $totalHistori = $allHistori->count();

        // Group by jenis histori
        $byJenisHistori = $allHistori->groupBy('jenis_histori')->map(function($group) {
            return $group->count();
        })->toArray();

        // Count by opname status
        $opnameCount = $allHistori->where('di_opname', 'Ya')->count();
        $nonOpnameCount = $totalHistori - $opnameCount;

        // Most recent histori
        $mostRecent = $allHistori->sortByDesc('tanggal')->first();
        $mostRecentDate = $mostRecent ? $mostRecent->tanggal->format('d M Y') : null;

        // Most common jenis histori
        $mostCommonJenis = collect($byJenisHistori)->sortDesc()->keys()->first();

        // Children with histori count
        $affectedChildrenCount = $allHistori->unique('id_anak')->count();

        // Unread count
        $unreadCount = $allHistori->where('is_read', false)->count();

        return [
            'total_histori' => $totalHistori,
            'by_jenis_histori' => $byJenisHistori,
            'opname_count' => $opnameCount,
            'non_opname_count' => $nonOpnameCount,
            'most_recent_date' => $mostRecentDate,
            'most_common_jenis' => $mostCommonJenis,
            'affected_children_count' => $affectedChildrenCount,
            'unread_count' => $unreadCount,
            'date_range' => [
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d')
            ]
        ];
    }

    private function getFilterOptions($shelterId)
    {
        // Available years
        $availableYears = Histori::whereHas('anak', function($query) use ($shelterId) {
            $query->where('id_shelter', $shelterId);
        })
        ->selectRaw('YEAR(tanggal) as year')
        ->distinct()
        ->orderBy('year', 'desc')
        ->pluck('year')
        ->toArray();

        // Available jenis histori
        $availableJenisHistori = Histori::whereHas('anak', function($query) use ($shelterId) {
            $query->where('id_shelter', $shelterId);
        })
        ->select('jenis_histori')
        ->distinct()
        ->whereNotNull('jenis_histori')
        ->orderBy('jenis_histori')
        ->pluck('jenis_histori')
        ->toArray();

        return [
            'available_years' => $availableYears,
            'available_jenis_histori' => $availableJenisHistori,
            'current_start_date' => date('Y-01-01'),
            'current_end_date' => date('Y-12-31')
        ];
    }
}