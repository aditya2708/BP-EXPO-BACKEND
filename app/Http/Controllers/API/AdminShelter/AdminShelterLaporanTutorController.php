<?php

namespace App\Http\Controllers\API\AdminShelter;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Tutor;
use App\Models\Aktivitas;
use App\Models\Absen;
use App\Models\AbsenUser;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;

class AdminShelterLaporanTutorController extends Controller
{
    public function getLaporanTutor(Request $request)
    {
        $shelterId = auth()->user()->adminShelter->id_shelter;
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');
        $jenisKegiatan = $request->get('jenis_kegiatan');
        $search = $request->get('search');
        $page = $request->get('page', 1);
        $perPage = $request->get('per_page', 15);

        // Set default date range to current year if not provided
        if (!$startDate || !$endDate) {
            $currentYear = date('Y');
            $startDate = $startDate ?: "{$currentYear}-01-01";
            $endDate = $endDate ?: "{$currentYear}-12-31";
        }

        $tutorsQuery = Tutor::where('id_shelter', $shelterId)
            ->select('id_tutor', 'nama', 'maple', 'foto');

        if ($search) {
            $tutorsQuery->where('nama', 'like', "%{$search}%");
        }

        $tutors = $tutorsQuery->paginate($perPage, ['*'], 'page', $page);

        $months = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
        ];

        $reportData = [];

        foreach ($tutors->items() as $tutor) {
            $tutorData = [
                'id_tutor' => $tutor->id_tutor,
                'nama' => $tutor->nama,
                'maple' => $tutor->maple,
                'foto_url' => $tutor->foto_url,
                'monthly_data' => [],
                'total_activities' => 0,
                'total_attended' => 0,
                'overall_percentage' => 0
            ];

            $totalActivities = $totalAttended = 0;

            // Parse date range to get months
            $start = \Carbon\Carbon::parse($startDate);
            $end = \Carbon\Carbon::parse($endDate);
            
            $currentDate = $start->copy()->startOfMonth();
            while ($currentDate <= $end) {
                $monthNum = $currentDate->month;
                $year = $currentDate->year;
                $monthName = $months[$monthNum];

                $activitiesQuery = Aktivitas::where('id_shelter', $shelterId)
                    ->whereYear('tanggal', $year)
                    ->whereMonth('tanggal', $monthNum)
                    ->whereDate('tanggal', '>=', $startDate)
                    ->whereDate('tanggal', '<=', $endDate);

                if ($jenisKegiatan) $activitiesQuery->where('jenis_kegiatan', $jenisKegiatan);

                $monthActivities = $activitiesQuery->pluck('id_aktivitas');
                $monthActivitiesCount = $monthActivities->count();

                $attendedCount = 0;
                if ($monthActivitiesCount > 0) {
                    $attendedCount = Absen::whereIn('id_aktivitas', $monthActivities)
                        ->whereHas('absenUser', function($query) use ($tutor) {
                            $query->where('id_tutor', $tutor->id_tutor);
                        })
                        ->whereIn('absen', [Absen::TEXT_YA, Absen::TEXT_TERLAMBAT])
                        ->count();
                }

                $percentage = $monthActivitiesCount > 0 ? round(($attendedCount / $monthActivitiesCount) * 100, 1) : 0;

                $tutorData['monthly_data'][$monthNum] = [
                    'month_name' => $monthName,
                    'month_number' => $monthNum,
                    'activities_count' => $monthActivitiesCount,
                    'attended_count' => $attendedCount,
                    'percentage' => $percentage
                ];

                $totalActivities += $monthActivitiesCount;
                $totalAttended += $attendedCount;

                $currentDate->addMonth();
            }

            $tutorData['total_activities'] = $totalActivities;
            $tutorData['total_attended'] = $totalAttended;
            $tutorData['overall_percentage'] = $totalActivities > 0 ? round(($totalAttended / $totalActivities) * 100, 1) : 0;

            $reportData[] = $tutorData;
        }

        usort($reportData, function($a, $b) {
            return $b['overall_percentage'] <=> $a['overall_percentage'];
        });

        $availableYears = Aktivitas::where('id_shelter', $shelterId)
            ->selectRaw('YEAR(tanggal) as year')
            ->distinct()
            ->orderBy('year', 'desc')
            ->pluck('year')
            ->toArray();

        $availableActivityTypes = Aktivitas::where('id_shelter', $shelterId)
            ->select('jenis_kegiatan')
            ->distinct()
            ->whereNotNull('jenis_kegiatan')
            ->pluck('jenis_kegiatan')
            ->toArray();

        $summary = [
            'total_tutors' => $tutors->total(),
            'average_attendance' => count($reportData) > 0 ? round(collect($reportData)->avg('overall_percentage'), 1) : 0,
            'highest_attendance' => count($reportData) > 0 ? collect($reportData)->max('overall_percentage') : 0,
            'lowest_attendance' => count($reportData) > 0 ? collect($reportData)->min('overall_percentage') : 0,
            'total_activities' => collect($reportData)->sum('total_activities'),
            'date_range' => [
                'start_date' => $startDate,
                'end_date' => $endDate
            ]
        ];

        return response()->json([
            'message' => 'Laporan tutor retrieved successfully',
            'data' => [
                'tutors' => $reportData,
                'summary' => $summary,
                'pagination' => [
                    'current_page' => $tutors->currentPage(),
                    'last_page' => $tutors->lastPage(),
                    'per_page' => $tutors->perPage(),
                    'total' => $tutors->total()
                ],
                'filter_options' => [
                    'available_years' => $availableYears,
                    'available_activity_types' => $availableActivityTypes,
                    'current_activity_type' => $jenisKegiatan
                ],
                'months' => $months
            ]
        ]);
    }

    public function getTutorDetailReport(Request $request, $tutorId)
    {
        $shelterId = auth()->user()->adminShelter->id_shelter;
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');
        $jenisKegiatan = $request->get('jenis_kegiatan');

        // Set default date range to current year if not provided
        if (!$startDate || !$endDate) {
            $currentYear = date('Y');
            $startDate = $startDate ?: "{$currentYear}-01-01";
            $endDate = $endDate ?: "{$currentYear}-12-31";
        }

        $tutor = Tutor::where('id_tutor', $tutorId)
            ->where('id_shelter', $shelterId)
            ->first();

        if (!$tutor) {
            return response()->json(['message' => 'Tutor not found or not accessible'], 404);
        }

        $activitiesQuery = Aktivitas::where('id_shelter', $shelterId)
            ->whereDate('tanggal', '>=', $startDate)
            ->whereDate('tanggal', '<=', $endDate);

        if ($jenisKegiatan) $activitiesQuery->where('jenis_kegiatan', $jenisKegiatan);

        $activities = $activitiesQuery->orderBy('tanggal', 'desc')->get();

        $detailRecords = [];
        foreach ($activities as $activity) {
            $absenRecord = Absen::where('id_aktivitas', $activity->id_aktivitas)
                ->whereHas('absenUser', function($query) use ($tutorId) {
                    $query->where('id_tutor', $tutorId);
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
            'message' => 'Tutor detail report retrieved successfully',
            'data' => [
                'tutor' => [
                    'id_tutor' => $tutor->id_tutor,
                    'nama' => $tutor->nama,
                    'maple' => $tutor->maple,
                    'foto_url' => $tutor->foto_url
                ],
                'attendance_records' => $detailRecords,
                'filter' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'jenis_kegiatan' => $jenisKegiatan
                ]
            ]
        ]);
    }

    public function exportTutorData(Request $request)
    {
        $user = auth()->user();
        $adminShelter = $user->adminShelter;
        $shelterId = $adminShelter->id_shelter;

        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');
        $jenisKegiatan = $request->get('jenis_kegiatan');
        $search = $request->get('search');
        $format = $request->get('format', 'json');

        // Set default date range
        if (!$startDate || !$endDate) {
            $currentYear = date('Y');
            $startDate = $startDate ?: "{$currentYear}-01-01";
            $endDate = $endDate ?: "{$currentYear}-12-31";
        }

        \Log::info('Export tutor format requested: ' . $format, ['all_params' => $request->all()]);

        try {
            // Get tutors with attendance data
            $tutorsQuery = Tutor::where('id_shelter', $shelterId)
                ->with(['shelter']);

            if ($search) {
                $tutorsQuery->where('nama', 'like', "%{$search}%");
            }

            $tutors = $tutorsQuery->get();

            $months = [
                1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
                5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
                9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
            ];

            // Get summary data
            $summary = $this->getTutorSummary($shelterId, $startDate, $endDate, $jenisKegiatan, $search);

            $exportData = [
                'export_date' => now()->format('Y-m-d H:i:s'),
                'shelter' => $adminShelter->shelter->nama_shelter ?? 'Unknown',
                'shelter_coordinator' => $adminShelter->shelter->nama_koordinator ?? '',
                'filters' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'jenis_kegiatan' => $jenisKegiatan,
                    'search' => $search
                ],
                'summary' => $summary,
                'total_records' => $tutors->count(),
                'tutors' => []
            ];

            // Process each tutor
            foreach ($tutors as $tutor) {
                $tutorData = [
                    'id_tutor' => $tutor->id_tutor,
                    'nama' => $tutor->nama,
                    'mata_pelajaran' => $tutor->maple ?? '',
                    'monthly_data' => [],
                    'total_activities' => 0,
                    'total_attended' => 0,
                    'overall_percentage' => 0
                ];

                $totalActivities = $totalAttended = 0;

                // Calculate monthly data
                $start = \Carbon\Carbon::parse($startDate);
                $end = \Carbon\Carbon::parse($endDate);
                
                $currentDate = $start->copy()->startOfMonth();
                while ($currentDate <= $end) {
                    $monthNum = $currentDate->month;
                    $year = $currentDate->year;
                    $monthName = $months[$monthNum];

                    $activitiesQuery = Aktivitas::where('id_shelter', $shelterId)
                        ->whereYear('tanggal', $year)
                        ->whereMonth('tanggal', $monthNum)
                        ->whereDate('tanggal', '>=', $startDate)
                        ->whereDate('tanggal', '<=', $endDate);

                    if ($jenisKegiatan) $activitiesQuery->where('jenis_kegiatan', $jenisKegiatan);

                    $monthActivities = $activitiesQuery->pluck('id_aktivitas');
                    $monthActivitiesCount = $monthActivities->count();

                    $attendedCount = 0;
                    if ($monthActivitiesCount > 0) {
                        $attendedCount = Absen::whereIn('id_aktivitas', $monthActivities)
                            ->whereHas('absenUser', function($query) use ($tutor) {
                                $query->where('id_tutor', $tutor->id_tutor);
                            })
                            ->whereIn('absen', [Absen::TEXT_YA, Absen::TEXT_TERLAMBAT])
                            ->count();
                    }

                    $percentage = $monthActivitiesCount > 0 ? round(($attendedCount / $monthActivitiesCount) * 100, 1) : 0;

                    $tutorData['monthly_data'][] = [
                        'bulan' => $monthName,
                        'bulan_number' => $monthNum,
                        'aktivitas' => $monthActivitiesCount,
                        'hadir' => $attendedCount,
                        'persentase' => $percentage
                    ];

                    $totalActivities += $monthActivitiesCount;
                    $totalAttended += $attendedCount;

                    $currentDate->addMonth();
                }

                $tutorData['total_activities'] = $totalActivities;
                $tutorData['total_attended'] = $totalAttended;
                $tutorData['overall_percentage'] = $totalActivities > 0 ? round(($totalAttended / $totalActivities) * 100, 1) : 0;

                $exportData['tutors'][] = $tutorData;
            }

            // Return based on format
            if ($format === 'pdf') {
                \Log::info('Generating PDF format for tutor report');
                return $this->generateTutorPDF($exportData);
            }

            // Default JSON response
            \Log::info('Returning JSON format for tutor report');
            return response()->json([
                'message' => 'Export tutor data prepared successfully',
                'data' => $exportData
            ]);

        } catch (\Exception $e) {
            \Log::error("Export tutor data failed", [
                'shelter_id' => $shelterId,
                'format' => $format,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Export failed: ' . $e->getMessage()
            ], 500);
        }
    }

    private function getTutorSummary($shelterId, $startDate, $endDate, $jenisKegiatan = null, $search = null)
    {
        $tutorsQuery = Tutor::where('id_shelter', $shelterId);
        
        if ($search) {
            $tutorsQuery->where('nama', 'like', "%{$search}%");
        }
        
        $totalTutors = $tutorsQuery->count();

        $activitiesQuery = Aktivitas::where('id_shelter', $shelterId)
            ->whereDate('tanggal', '>=', $startDate)
            ->whereDate('tanggal', '<=', $endDate);

        if ($jenisKegiatan) $activitiesQuery->where('jenis_kegiatan', $jenisKegiatan);

        $totalActivities = $activitiesQuery->count();

        // Calculate average attendance across all tutors
        $tutors = $tutorsQuery->get();
        $attendancePercentages = [];

        foreach ($tutors as $tutor) {
            $activities = $activitiesQuery->pluck('id_aktivitas');
            $attendedCount = 0;
            
            if ($activities->count() > 0) {
                $attendedCount = Absen::whereIn('id_aktivitas', $activities)
                    ->whereHas('absenUser', function($query) use ($tutor) {
                        $query->where('id_tutor', $tutor->id_tutor);
                    })
                    ->whereIn('absen', [Absen::TEXT_YA, Absen::TEXT_TERLAMBAT])
                    ->count();
            }

            $percentage = $activities->count() > 0 ? round(($attendedCount / $activities->count()) * 100, 1) : 0;
            $attendancePercentages[] = $percentage;
        }

        return [
            'total_tutors' => $totalTutors,
            'total_activities' => $totalActivities,
            'average_attendance' => count($attendancePercentages) > 0 ? round(collect($attendancePercentages)->avg(), 1) : 0,
            'highest_attendance' => count($attendancePercentages) > 0 ? collect($attendancePercentages)->max() : 0,
            'lowest_attendance' => count($attendancePercentages) > 0 ? collect($attendancePercentages)->min() : 0
        ];
    }

    private function generateTutorPDF($data)
    {
        try {
            $pdf = PDF::loadView('reports.tutor-report', compact('data'))
                ->setPaper('a4', 'landscape')
                ->setOptions([
                    'defaultFont' => 'sans-serif',
                    'isHtml5ParserEnabled' => true,
                    'isRemoteEnabled' => true
                ]);

            $filename = 'laporan-tutor-' . date('Y-m-d') . '.pdf';

            return $pdf->download($filename);

        } catch (\Exception $e) {
            \Log::error("Tutor PDF generation failed", [
                'error' => $e->getMessage(),
                'data_count' => count($data['tutors'])
            ]);

            return response()->json([
                'message' => 'PDF generation failed: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getJenisKegiatanOptions(Request $request)
    {
        $shelterId = auth()->user()->adminShelter->id_shelter;

        $jenisKegiatan = Aktivitas::where('id_shelter', $shelterId)
            ->select('jenis_kegiatan')
            ->distinct()
            ->whereNotNull('jenis_kegiatan')
            ->orderBy('jenis_kegiatan')
            ->pluck('jenis_kegiatan')
            ->toArray();

        return response()->json([
            'message' => 'Jenis kegiatan options retrieved successfully',
            'data' => $jenisKegiatan
        ]);
    }

    public function getAvailableYears(Request $request)
    {
        $shelterId = auth()->user()->adminShelter->id_shelter;

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