<?php

namespace App\Http\Controllers\API\AdminShelter;

use App\Http\Controllers\Controller;
use App\Models\Anak;
use App\Models\Raport;
use App\Models\RaportDetail;
use App\Models\Semester;
use App\Models\Materi;
use App\Models\AdminShelter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class AdminShelterLaporanRaportController extends Controller
{
    /**
     * Get laporan raport with date range filtering (new approach)
     */
    public function getLaporanRaport(Request $request)
    {
        $user = Auth::user();
        $adminShelter = $user->adminShelter;
        $shelterId = $adminShelter->id_shelter;

        // Date range filters (default to current year)
        $startDate = $request->get('start_date', date('Y-01-01'));
        $endDate = $request->get('end_date', date('Y-12-31'));
        $mataPelajaran = $request->get('mata_pelajaran', null);
        $search = $request->get('search');
        $perPage = $request->get('per_page', 15);

        // Validate and parse dates
        try {
            $startDate = Carbon::parse($startDate)->startOfDay();
            $endDate = Carbon::parse($endDate)->endOfDay();
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Invalid date format'
            ], 400);
        }

        // Get active children in this shelter with search
        $childrenQuery = Anak::where('id_shelter', $shelterId)
            ->whereIn('status_validasi', Anak::STATUS_AKTIF)
            ->select('id_anak', 'full_name', 'nick_name', 'foto');

        if ($search) {
            $childrenQuery->where(function($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                  ->orWhere('nick_name', 'like', "%{$search}%");
            });
        }

        $children = $childrenQuery->paginate($perPage);
        $reportData = [];

        foreach ($children as $child) {
            // Get raport data for this child within date range
            $raportQuery = Raport::where('id_anak', $child->id_anak)
                ->whereHas('semester', function($query) use ($startDate, $endDate) {
                    $query->whereBetween('tanggal_mulai', [$startDate, $endDate])
                          ->orWhereBetween('tanggal_selesai', [$startDate, $endDate]);
                })
                ->with(['semester', 'raportDetail' => function($query) use ($mataPelajaran) {
                    if ($mataPelajaran) {
                        $query->where('mata_pelajaran', $mataPelajaran);
                    }
                }]);

            $raportList = $raportQuery->get();

            // Calculate statistics
            $totalRaport = $raportList->count();
            $publishedCount = $raportList->where('status', 'published')->count();
            $draftCount = $raportList->where('status', 'draft')->count();

            // Calculate average grade across all raport
            $allGrades = [];
            $raportData = [];
            
            foreach ($raportList as $raport) {
                $subjectGrades = $raport->raportDetail->pluck('nilai_akhir')->toArray();
                $raportAverage = count($subjectGrades) > 0 ? array_sum($subjectGrades) / count($subjectGrades) : 0;
                $allGrades = array_merge($allGrades, $subjectGrades);

                $raportData[] = [
                    'id_raport' => $raport->id_raport,
                    'semester' => $raport->semester ? $raport->semester->nama_semester : 'Unknown',
                    'tahun_ajaran' => $raport->semester ? $raport->semester->tahun_ajaran : 'Unknown',
                    'status' => $raport->status,
                    'tanggal_terbit' => $raport->tanggal_terbit ? $raport->tanggal_terbit->format('Y-m-d') : null,
                    'subjects_count' => $raport->raportDetail->count(),
                    'average_grade' => round($raportAverage, 2),
                    'created_at' => $raport->created_at->format('Y-m-d')
                ];
            }

            $childAverage = count($allGrades) > 0 ? round(array_sum($allGrades) / count($allGrades), 2) : 0;

            // Get latest raport info
            $latestRaport = $raportList->sortByDesc('created_at')->first();

            $childData = [
                'id_anak' => $child->id_anak,
                'full_name' => $child->full_name,
                'nick_name' => $child->nick_name,
                'foto_url' => $child->foto_url,
                'total_raport' => $totalRaport,
                'published_count' => $publishedCount,
                'draft_count' => $draftCount,
                'average_grade' => $childAverage,
                'latest_raport_date' => $latestRaport ? $latestRaport->created_at->format('Y-m-d') : null,
                'latest_semester' => $latestRaport && $latestRaport->semester ? $latestRaport->semester->nama_semester : null,
                'raport_data' => $raportData
            ];

            $reportData[] = $childData;
        }

        // Filter out children with no raport data
        $reportData = array_filter($reportData, function($child) {
            return $child['total_raport'] > 0;
        });

        // Sort by average grade descending
        usort($reportData, function($a, $b) {
            return $b['average_grade'] <=> $a['average_grade'];
        });

        // Calculate summary statistics
        $totalChildren = count($reportData);
        $totalRaport = array_sum(array_column($reportData, 'total_raport'));
        $publishedRaport = array_sum(array_column($reportData, 'published_count'));
        $draftRaport = array_sum(array_column($reportData, 'draft_count'));
        $averageGrade = $totalChildren > 0 ? round(array_sum(array_column($reportData, 'average_grade')) / $totalChildren, 2) : 0;

        // Calculate passing percentage (assuming KKM is 70)
        $allGrades = [];
        foreach ($reportData as $child) {
            foreach ($child['raport_data'] as $raport) {
                if ($raport['average_grade'] > 0) {
                    $allGrades[] = $raport['average_grade'];
                }
            }
        }
        $passedSubjects = count(array_filter($allGrades, function($grade) { return $grade >= 70; }));
        $passingPercentage = count($allGrades) > 0 ? round(($passedSubjects / count($allGrades)) * 100, 1) : 0;

        // Get filter options
        $availableMataPelajaran = RaportDetail::whereHas('raport.anak', function($query) use ($shelterId) {
                $query->where('id_shelter', $shelterId);
            })
            ->distinct()
            ->orderBy('mata_pelajaran')
            ->pluck('mata_pelajaran')
            ->toArray();

        $availableYears = Semester::distinct()
            ->orderBy('tahun_ajaran', 'desc')
            ->pluck('tahun_ajaran')
            ->toArray();

        $summary = [
            'total_children' => $totalChildren,
            'total_raport' => $totalRaport,
            'published_raport' => $publishedRaport,
            'draft_raport' => $draftRaport,
            'average_grade' => $averageGrade,
            'passing_percentage' => $passingPercentage
        ];

        return response()->json([
            'message' => 'Laporan raport retrieved successfully',
            'data' => [
                'children' => array_values($reportData),
                'summary' => $summary,
                'pagination' => [
                    'current_page' => $children->currentPage(),
                    'last_page' => $children->lastPage(),
                    'per_page' => $children->perPage(),
                    'total' => count($reportData)
                ],
                'filter_options' => [
                    'available_mata_pelajaran' => $availableMataPelajaran,
                    'available_years' => $availableYears,
                    'current_start_date' => $startDate->format('Y-m-d'),
                    'current_end_date' => $endDate->format('Y-m-d'),
                    'current_mata_pelajaran' => $mataPelajaran
                ]
            ]
        ]);
    }

    /**
     * Get detailed raport report for specific child (updated for date range)
     */
    public function getChildDetailReport($childId, Request $request)
    {
        $user = Auth::user();
        $adminShelter = $user->adminShelter;
        $shelterId = $adminShelter->id_shelter;

        $startDate = $request->get('start_date', date('Y-01-01'));
        $endDate = $request->get('end_date', date('Y-12-31'));
        $mataPelajaran = $request->get('mata_pelajaran', null);

        try {
            $startDate = Carbon::parse($startDate)->startOfDay();
            $endDate = Carbon::parse($endDate)->endOfDay();
        } catch (\Exception $e) {
            return response()->json(['message' => 'Invalid date format'], 400);
        }

        // Verify child belongs to this shelter
        $child = Anak::where('id_anak', $childId)
            ->where('id_shelter', $shelterId)
            ->whereIn('status_validasi', Anak::STATUS_AKTIF)
            ->first();

        if (!$child) {
            return response()->json(['message' => 'Child not found or not accessible'], 404);
        }

        // Get raport data within date range
        $raportQuery = Raport::where('id_anak', $childId)
            ->whereHas('semester', function($query) use ($startDate, $endDate) {
                $query->whereBetween('tanggal_mulai', [$startDate, $endDate])
                      ->orWhereBetween('tanggal_selesai', [$startDate, $endDate]);
            })
            ->with(['semester', 'raportDetail' => function($query) use ($mataPelajaran) {
                if ($mataPelajaran) {
                    $query->where('mata_pelajaran', $mataPelajaran);
                }
            }])
            ->orderBy('created_at', 'desc');

        $raportRecords = $raportQuery->get()->map(function($raport) {
            $subjectGrades = $raport->raportDetail->pluck('nilai_akhir')->toArray();
            $averageGrade = count($subjectGrades) > 0 ? round(array_sum($subjectGrades) / count($subjectGrades), 2) : 0;
            
            return [
                'id_raport' => $raport->id_raport,
                'semester' => [
                    'id_semester' => $raport->semester->id_semester,
                    'nama_semester' => $raport->semester->nama_semester,
                    'tahun_ajaran' => $raport->semester->tahun_ajaran,
                    'periode' => $raport->semester->periode
                ],
                'status' => $raport->status,
                'tanggal_terbit' => $raport->tanggal_terbit ? $raport->tanggal_terbit->format('Y-m-d') : null,
                'total_kehadiran' => $raport->total_kehadiran,
                'persentase_kehadiran' => $raport->persentase_kehadiran,
                'ranking' => $raport->ranking,
                'catatan_wali_kelas' => $raport->catatan_wali_kelas,
                'average_grade' => $averageGrade,
                'subjects' => $raport->raportDetail->map(function($detail) {
                    return [
                        'mata_pelajaran' => $detail->mata_pelajaran,
                        'nilai_akhir' => $detail->nilai_akhir,
                        'nilai_huruf' => $detail->nilai_huruf,
                        'kkm' => $detail->kkm,
                        'keterangan' => $detail->keterangan,
                        'is_passed' => $detail->nilai_akhir >= $detail->kkm
                    ];
                })
            ];
        });

        return response()->json([
            'message' => 'Child detail report retrieved successfully',
            'data' => [
                'child' => [
                    'id_anak' => $child->id_anak,
                    'full_name' => $child->full_name,
                    'nick_name' => $child->nick_name,
                    'foto_url' => $child->foto_url
                ],
                'raport_records' => $raportRecords,
                'filter' => [
                    'start_date' => $startDate->format('Y-m-d'),
                    'end_date' => $endDate->format('Y-m-d'),
                    'mata_pelajaran' => $mataPelajaran
                ]
            ]
        ]);
    }

    /**
     * Keep existing methods for backward compatibility
     */
    public function getSemesterOptions()
    {
        $semesters = Semester::orderBy('tahun_ajaran', 'desc')
            ->orderBy('periode', 'desc')
            ->get(['id_semester', 'nama_semester', 'tahun_ajaran', 'periode']);

        return response()->json([
            'success' => true,
            'data' => $semesters
        ]);
    }

    public function getMataPelajaranOptions()
    {
        $mataPelajaran = Materi::distinct()
            ->orderBy('mata_pelajaran')
            ->pluck('mata_pelajaran');

        return response()->json([
            'success' => true,
            'data' => $mataPelajaran
        ]);
    }

    public function getAvailableYears()
    {
        $user = Auth::user();
        $adminShelter = $user->adminShelter;
        
        $years = Raport::whereHas('anak', function($query) use ($adminShelter) {
                $query->where('id_shelter', $adminShelter->id_shelter);
            })
            ->with('semester')
            ->get()
            ->pluck('semester.tahun_ajaran')
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->reverse();

        return response()->json([
            'success' => true,
            'data' => $years
        ]);
    }
}