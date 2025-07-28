<?php

namespace App\Http\Controllers\API\AdminShelter;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Anak;
use App\Models\Aktivitas;
use App\Models\Absen;
use App\Models\AbsenUser;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;

class AdminShelterLaporanAnakController extends Controller
{
    public function getLaporanAnakBinaan(Request $request)
    {
        $user = auth()->user();
        $adminShelter = $user->adminShelter;
        $shelterId = $adminShelter->id_shelter;

        // Date range filters (default to current year if not provided)
        $startDate = $request->get('start_date', date('Y-01-01'));
        $endDate = $request->get('end_date', date('Y-12-31'));
        $jenisKegiatan = $request->get('jenisKegiatan', null);
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

        // Get all months in the date range
        $monthsInRange = $this->getMonthsInRange($startDate, $endDate);

        foreach ($children as $child) {
            $childData = [
                'id_anak' => $child->id_anak,
                'full_name' => $child->full_name,
                'nick_name' => $child->nick_name,
                'foto_url' => $child->foto_url,
                'monthly_data' => [],
                'total_activities' => 0,
                'total_attended' => 0,
                'overall_percentage' => 0
            ];

            $totalActivities = 0;
            $totalAttended = 0;

            foreach ($monthsInRange as $monthData) {
                $monthStart = Carbon::create($monthData['year'], $monthData['month'], 1)->startOfMonth();
                $monthEnd = Carbon::create($monthData['year'], $monthData['month'], 1)->endOfMonth();
                
                // Ensure we don't go beyond the specified date range
                $monthStart = $monthStart->max($startDate);
                $monthEnd = $monthEnd->min($endDate);

                // Get activities for this month period
                $activitiesQuery = Aktivitas::where('id_shelter', $shelterId)
                    ->whereBetween('tanggal', [$monthStart, $monthEnd]);

                if ($jenisKegiatan) {
                    $activitiesQuery->where('jenis_kegiatan', $jenisKegiatan);
                }

                $monthActivities = $activitiesQuery->pluck('id_aktivitas');
                $monthActivitiesCount = $monthActivities->count();

                // Get attendance for this child in this month
                $attendedCount = 0;
                if ($monthActivitiesCount > 0) {
                    $attendedCount = Absen::whereIn('id_aktivitas', $monthActivities)
                        ->whereHas('absenUser', function($query) use ($child) {
                            $query->where('id_anak', $child->id_anak);
                        })
                        ->whereIn('absen', [Absen::TEXT_YA, Absen::TEXT_TERLAMBAT])
                        ->count();
                }

                $percentage = $monthActivitiesCount > 0 ? round(($attendedCount / $monthActivitiesCount) * 100, 1) : 0;

                $childData['monthly_data'][$monthData['key']] = [
                    'month_name' => $monthData['name'],
                    'month_number' => $monthData['month'],
                    'year' => $monthData['year'],
                    'activities_count' => $monthActivitiesCount,
                    'attended_count' => $attendedCount,
                    'percentage' => $percentage
                ];

                $totalActivities += $monthActivitiesCount;
                $totalAttended += $attendedCount;
            }

            $childData['total_activities'] = $totalActivities;
            $childData['total_attended'] = $totalAttended;
            $childData['overall_percentage'] = $totalActivities > 0 ? round(($totalAttended / $totalActivities) * 100, 1) : 0;

            $reportData[] = $childData;
        }

        // Sort by overall percentage descending
        usort($reportData, function($a, $b) {
            return $b['overall_percentage'] <=> $a['overall_percentage'];
        });

        // Get available years (years that have activities)
        $availableYears = Aktivitas::where('id_shelter', $shelterId)
            ->selectRaw('YEAR(tanggal) as year')
            ->distinct()
            ->orderBy('year', 'desc')
            ->pluck('year')
            ->toArray();

        // Get available activity types
        $availableActivityTypes = Aktivitas::where('id_shelter', $shelterId)
            ->select('jenis_kegiatan')
            ->distinct()
            ->whereNotNull('jenis_kegiatan')
            ->pluck('jenis_kegiatan')
            ->toArray();

        // Summary statistics
        $summary = [
            'total_children' => $children->total(),
            'average_attendance' => count($reportData) > 0 ? round(collect($reportData)->avg('overall_percentage'), 1) : 0,
            'highest_attendance' => count($reportData) > 0 ? collect($reportData)->max('overall_percentage') : 0,
            'lowest_attendance' => count($reportData) > 0 ? collect($reportData)->min('overall_percentage') : 0,
            'total_activities' => collect($reportData)->sum('total_activities'),
            'date_range' => [
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d')
            ]
        ];

        return response()->json([
            'message' => 'Laporan anak binaan retrieved successfully',
            'data' => [
                'children' => $reportData,
                'summary' => $summary,
                'pagination' => [
                    'current_page' => $children->currentPage(),
                    'last_page' => $children->lastPage(),
                    'per_page' => $children->perPage(),
                    'total' => $children->total()
                ],
                'filter_options' => [
                    'available_years' => $availableYears,
                    'available_activity_types' => $availableActivityTypes,
                    'current_start_date' => $startDate->format('Y-m-d'),
                    'current_end_date' => $endDate->format('Y-m-d'),
                    'current_activity_type' => $jenisKegiatan
                ]
            ]
        ]);
    }

    public function exportLaporanAnakBinaan(Request $request)
    {
        $user = auth()->user();
        $adminShelter = $user->adminShelter;
        $shelterId = $adminShelter->id_shelter;

        $startDate = $request->get('start_date', date('Y-01-01'));
        $endDate = $request->get('end_date', date('Y-12-31'));
        $jenisKegiatan = $request->get('jenisKegiatan', null);
        $search = $request->get('search', null);
        $format = $request->get('format', 'json');

        try {
            $startDate = Carbon::parse($startDate)->startOfDay();
            $endDate = Carbon::parse($endDate)->endOfDay();
        } catch (\Exception $e) {
            return response()->json(['message' => 'Invalid date format'], 400);
        }

        try {
            // Get active children with search filter
            $childrenQuery = Anak::where('id_shelter', $shelterId)
                ->whereIn('status_validasi', Anak::STATUS_AKTIF)
                ->select('id_anak', 'full_name', 'nick_name', 'foto');

            if ($search) {
                $childrenQuery->where(function($q) use ($search) {
                    $q->where('full_name', 'like', "%{$search}%")
                      ->orWhere('nick_name', 'like', "%{$search}%");
                });
            }

            $children = $childrenQuery->get();
            $monthsInRange = $this->getMonthsInRange($startDate, $endDate);
            $reportData = [];

            foreach ($children as $child) {
                $childData = [
                    'id_anak' => $child->id_anak,
                    'nama_lengkap' => $child->full_name,
                    'nama_panggilan' => $child->nick_name,
                    'monthly_data' => [],
                    'total_activities' => 0,
                    'total_attended' => 0,
                    'overall_percentage' => 0
                ];

                $totalActivities = 0;
                $totalAttended = 0;

                foreach ($monthsInRange as $monthData) {
                    $monthStart = Carbon::create($monthData['year'], $monthData['month'], 1)->startOfMonth()->max($startDate);
                    $monthEnd = Carbon::create($monthData['year'], $monthData['month'], 1)->endOfMonth()->min($endDate);

                    $activitiesQuery = Aktivitas::where('id_shelter', $shelterId)
                        ->whereBetween('tanggal', [$monthStart, $monthEnd]);

                    if ($jenisKegiatan) {
                        $activitiesQuery->where('jenis_kegiatan', $jenisKegiatan);
                    }

                    $monthActivities = $activitiesQuery->pluck('id_aktivitas');
                    $monthActivitiesCount = $monthActivities->count();

                    $attendedCount = 0;
                    if ($monthActivitiesCount > 0) {
                        $attendedCount = Absen::whereIn('id_aktivitas', $monthActivities)
                            ->whereHas('absenUser', function($query) use ($child) {
                                $query->where('id_anak', $child->id_anak);
                            })
                            ->whereIn('absen', [Absen::TEXT_YA, Absen::TEXT_TERLAMBAT])
                            ->count();
                    }

                    $percentage = $monthActivitiesCount > 0 ? round(($attendedCount / $monthActivitiesCount) * 100, 1) : 0;

                    $childData['monthly_data'][] = [
                        'month_name' => $monthData['name'],
                        'activities_count' => $monthActivitiesCount,
                        'attended_count' => $attendedCount,
                        'percentage' => $percentage
                    ];

                    $totalActivities += $monthActivitiesCount;
                    $totalAttended += $attendedCount;
                }

                $childData['total_activities'] = $totalActivities;
                $childData['total_attended'] = $totalAttended;
                $childData['overall_percentage'] = $totalActivities > 0 ? round(($totalAttended / $totalActivities) * 100, 1) : 0;

                $reportData[] = $childData;
            }

            // Sort by overall percentage descending
            usort($reportData, function($a, $b) {
                return $b['overall_percentage'] <=> $a['overall_percentage'];
            });

            // Calculate summary
            $summary = [
                'total_children' => count($reportData),
                'average_attendance' => count($reportData) > 0 ? round(collect($reportData)->avg('overall_percentage'), 1) : 0,
                'highest_attendance' => count($reportData) > 0 ? collect($reportData)->max('overall_percentage') : 0,
                'lowest_attendance' => count($reportData) > 0 ? collect($reportData)->min('overall_percentage') : 0,
                'total_activities' => collect($reportData)->sum('total_activities'),
                'total_attended' => collect($reportData)->sum('total_attended')
            ];

            $exportData = [
                'export_date' => now()->format('Y-m-d H:i:s'),
                'shelter' => $adminShelter->shelter->nama_shelter ?? 'Unknown',
                'shelter_coordinator' => $adminShelter->shelter->nama_koordinator ?? '',
                'filters' => [
                    'start_date' => $startDate->format('Y-m-d'),
                    'end_date' => $endDate->format('Y-m-d'),
                    'jenis_kegiatan' => $jenisKegiatan,
                    'search' => $search
                ],
                'summary' => $summary,
                'months' => $monthsInRange,
                'total_records' => count($reportData),
                'children' => $reportData
            ];

            if ($format === 'pdf') {
                return $this->generateAttendancePDF($exportData);
            }

            return response()->json([
                'message' => 'Export data prepared successfully',
                'data' => $exportData
            ]);

        } catch (\Exception $e) {
            \Log::error("Export attendance data failed", [
                'shelter_id' => $shelterId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Export failed: ' . $e->getMessage()
            ], 500);
        }
    }

    private function generateAttendancePDF($data)
    {
        try {
            $pdf = Pdf::loadView('reports.anak-binaan-report', compact('data'))
                ->setPaper('a4', 'landscape')
                ->setOptions([
                    'defaultFont' => 'sans-serif',
                    'isHtml5ParserEnabled' => true,
                    'isRemoteEnabled' => true
                ]);

            $filename = 'laporan-anak-binaan-' . date('Y-m-d') . '.pdf';
            return $pdf->download($filename);

        } catch (\Exception $e) {
            \Log::error("PDF generation failed", [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'PDF generation failed: ' . $e->getMessage()
            ], 500);
        }
    }

    private function getMonthsInRange(Carbon $startDate, Carbon $endDate)
    {
        $months = [];
        $current = $startDate->copy()->startOfMonth();
        $end = $endDate->copy()->endOfMonth();

        while ($current <= $end) {
            $months[] = [
                'key' => $current->format('Y-m'),
                'name' => $current->format('M Y'),
                'month' => $current->month,
                'year' => $current->year
            ];
            $current->addMonth();
        }

        return $months;
    }

    public function getChildDetailReport(Request $request, $childId)
    {
        $user = auth()->user();
        $adminShelter = $user->adminShelter;
        $shelterId = $adminShelter->id_shelter;

        $startDate = $request->get('start_date', date('Y-01-01'));
        $endDate = $request->get('end_date', date('Y-12-31'));
        $jenisKegiatan = $request->get('jenisKegiatan', null);

        // Validate and parse dates
        try {
            $startDate = Carbon::parse($startDate)->startOfDay();
            $endDate = Carbon::parse($endDate)->endOfDay();
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Invalid date format'
            ], 400);
        }

        // Verify child belongs to this shelter
        $child = Anak::where('id_anak', $childId)
            ->where('id_shelter', $shelterId)
            ->whereIn('status_validasi', Anak::STATUS_AKTIF)
            ->first();

        if (!$child) {
            return response()->json([
                'message' => 'Child not found or not accessible'
            ], 404);
        }

        // Get detailed attendance records
        $activitiesQuery = Aktivitas::where('id_shelter', $shelterId)
            ->whereBetween('tanggal', [$startDate, $endDate]);

        if ($jenisKegiatan) {
            $activitiesQuery->where('jenis_kegiatan', $jenisKegiatan);
        }

        $activities = $activitiesQuery->orderBy('tanggal', 'desc')->get();

        $detailRecords = [];
        foreach ($activities as $activity) {
            // Get attendance record for this child
            $absenRecord = Absen::where('id_aktivitas', $activity->id_aktivitas)
                ->whereHas('absenUser', function($query) use ($childId) {
                    $query->where('id_anak', $childId);
                })
                ->first();

            $detailRecords[] = [
                'id_aktivitas' => $activity->id_aktivitas,
                'tanggal' => $activity->tanggal->format('Y-m-d'),
                'tanggal_formatted' => $activity->tanggal->format('d M Y'),
                'jenis_kegiatan' => $activity->jenis_kegiatan,
                'materi' => $activity->materi,
                'start_time' => $activity->start_time,
                'end_time' => $activity->end_time,
                'status_kehadiran' => $absenRecord ? $absenRecord->absen : 'Tidak',
                'time_arrived' => $absenRecord ? $absenRecord->time_arrived : null,
                'is_verified' => $absenRecord ? $absenRecord->is_verified : false
            ];
        }

        return response()->json([
            'message' => 'Child detail report retrieved successfully',
            'data' => [
                'child' => [
                    'id_anak' => $child->id_anak,
                    'full_name' => $child->full_name,
                    'nick_name' => $child->nick_name,
                    'foto_url' => $child->foto_url
                ],
                'attendance_records' => $detailRecords,
                'filter' => [
                    'start_date' => $startDate->format('Y-m-d'),
                    'end_date' => $endDate->format('Y-m-d'),
                    'jenis_kegiatan' => $jenisKegiatan
                ]
            ]
        ]);
    }

    // Keep existing methods for backward compatibility
    public function getJenisKegiatanOptions(Request $request)
    {
        $user = auth()->user();
        $adminShelter = $user->adminShelter;
        $shelterId = $adminShelter->id_shelter;

        $jenisKegiatan = Aktivitas::where('id_shelter', $shelterId)
            ->select('jenis_kegiatan')
            ->distinct()
            ->whereNotNull('jenis_kegiatan')
            ->orderBy('jenis_kegiatan')
            ->pluck('jenis_kegiatan')
            ->toArray();

        return response()->json([
            'message' => 'Jenis kegiatan options retrieved successfully',
            'data' => [
                'availableActivityTypes' => $jenisKegiatan
            ]
        ]);
    }

    public function getAvailableYears(Request $request)
    {
        $user = auth()->user();
        $adminShelter = $user->adminShelter;
        $shelterId = $adminShelter->id_shelter;

        $years = Aktivitas::where('id_shelter', $shelterId)
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
}