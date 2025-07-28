<?php

namespace App\Http\Controllers\API\AdminShelter;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Aktivitas;
use App\Models\Absen;
use App\Models\Anak;
use Carbon\Carbon;

class AdminShelterLaporanAktivitasController extends Controller
{
    public function getLaporanAktivitas(Request $request)
    {
        $shelterId = auth()->user()->adminShelter->id_shelter;
        $year = $request->get('year', date('Y'));
        $jenisKegiatan = $request->get('jenis_kegiatan', null);
        $month = $request->get('month', null);

        $activitiesQuery = Aktivitas::where('id_shelter', $shelterId)
            ->whereYear('tanggal', $year);

        if ($jenisKegiatan) {
            $activitiesQuery->where('jenis_kegiatan', $jenisKegiatan);
        }

        if ($month) {
            $activitiesQuery->whereMonth('tanggal', $month);
        }

        $activities = $activitiesQuery->with(['tutor', 'absen.absenUser.anak'])
            ->orderBy('tanggal', 'desc')
            ->get();

        $reportData = [];
        $totalParticipants = 0;
        $totalPresent = 0;

        foreach ($activities as $activity) {
            $attendanceStats = $this->calculateAttendanceStats($activity);
            
            $activityData = [
                'id_aktivitas' => $activity->id_aktivitas,
                'tanggal' => $activity->tanggal->format('Y-m-d'),
                'tanggal_formatted' => $activity->tanggal->format('d M Y'),
                'jenis_kegiatan' => $activity->jenis_kegiatan,
                'level' => $activity->level,
                'nama_kelompok' => $activity->nama_kelompok,
                'materi' => $activity->materi,
                'tutor_nama' => $activity->tutor->nama ?? null,
                'start_time' => $activity->start_time,
                'end_time' => $activity->end_time,
                'foto_1_url' => $activity->foto_1_url,
                'foto_2_url' => $activity->foto_2_url,
                'foto_3_url' => $activity->foto_3_url,
                'attendance_stats' => $attendanceStats
            ];

            $totalParticipants += $attendanceStats['total_participants'];
            $totalPresent += $attendanceStats['present_count'];

            $reportData[] = $activityData;
        }

        // Calculate summary
        $summary = [
            'total_activities' => count($reportData),
            'total_participants' => $totalParticipants,
            'total_present' => $totalPresent,
            'average_attendance' => $totalParticipants > 0 ? round(($totalPresent / $totalParticipants) * 100, 1) : 0,
            'activities_this_month' => $this->getActivitiesThisMonth($shelterId),
            'most_active_type' => $this->getMostActiveType($shelterId, $year)
        ];

        // Get filter options
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
            ->orderBy('jenis_kegiatan')
            ->pluck('jenis_kegiatan')
            ->toArray();

        $months = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
        ];

        return response()->json([
            'message' => 'Laporan aktivitas retrieved successfully',
            'data' => [
                'activities' => $reportData,
                'summary' => $summary,
                'filter_options' => [
                    'available_years' => $availableYears,
                    'available_activity_types' => $availableActivityTypes,
                    'months' => $months,
                    'current_year' => (int) $year,
                    'current_activity_type' => $jenisKegiatan,
                    'current_month' => $month ? (int) $month : null
                ]
            ]
        ]);
    }

    public function getActivityDetailReport(Request $request, $activityId)
    {
        $shelterId = auth()->user()->adminShelter->id_shelter;

        $activity = Aktivitas::where('id_aktivitas', $activityId)
            ->where('id_shelter', $shelterId)
            ->with(['tutor', 'absen.absenUser.anak'])
            ->first();

        if (!$activity) {
            return response()->json(['message' => 'Activity not found or not accessible'], 404);
        }

        $attendanceRecords = [];
        foreach ($activity->absen as $absen) {
            $attendanceRecords[] = [
                'id_absen' => $absen->id_absen,
                'anak' => [
                    'id_anak' => $absen->absenUser->anak->id_anak,
                    'full_name' => $absen->anak->full_name,
                    'foto_url' => $absen->anak->foto_url
                ],
                'absen' => $absen->absen,
                'time_arrived' => $absen->time_arrived,
                'is_verified' => $absen->is_verified,
                'is_late' => $activity->isLate(Carbon::parse($absen->time_arrived)),
                'verified_by' => $absen->verified_by,
                'verified_at' => $absen->verified_at
            ];
        }

        $attendanceStats = $this->calculateAttendanceStats($activity);

        return response()->json([
            'message' => 'Activity detail report retrieved successfully',
            'data' => [
                'activity' => [
                    'id_aktivitas' => $activity->id_aktivitas,
                    'tanggal' => $activity->tanggal->format('Y-m-d'),
                    'tanggal_formatted' => $activity->tanggal->format('d M Y'),
                    'jenis_kegiatan' => $activity->jenis_kegiatan,
                    'level' => $activity->level,
                    'nama_kelompok' => $activity->nama_kelompok,
                    'materi' => $activity->materi,
                    'tutor_nama' => $activity->tutor->nama ?? null,
                    'tutor_maple' => $activity->tutor->maple ?? null,
                    'start_time' => $activity->start_time,
                    'end_time' => $activity->end_time,
                    'foto_1_url' => $activity->foto_1_url,
                    'foto_2_url' => $activity->foto_2_url,
                    'foto_3_url' => $activity->foto_3_url
                ],
                'attendance_records' => $attendanceRecords,
                'attendance_stats' => $attendanceStats
            ]
        ]);
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

    private function calculateAttendanceStats($activity)
    {
        $totalParticipants = $activity->absen->count();
        $presentCount = $activity->absen->whereIn('absen', [Absen::TEXT_YA, Absen::TEXT_TERLAMBAT])->count();
        $absentCount = $activity->absen->where('absen', Absen::TEXT_TIDAK)->count();
        $lateCount = $activity->absen->where('absen', Absen::TEXT_TERLAMBAT)->count();

        return [
            'total_participants' => $totalParticipants,
            'present_count' => $presentCount,
            'absent_count' => $absentCount,
            'late_count' => $lateCount,
            'attendance_percentage' => $totalParticipants > 0 ? round(($presentCount / $totalParticipants) * 100, 1) : 0
        ];
    }

    private function getActivitiesThisMonth($shelterId)
    {
        return Aktivitas::where('id_shelter', $shelterId)
            ->whereYear('tanggal', date('Y'))
            ->whereMonth('tanggal', date('m'))
            ->count();
    }

    private function getMostActiveType($shelterId, $year)
    {
        $mostActive = Aktivitas::where('id_shelter', $shelterId)
            ->whereYear('tanggal', $year)
            ->selectRaw('jenis_kegiatan, COUNT(*) as count')
            ->groupBy('jenis_kegiatan')
            ->orderBy('count', 'desc')
            ->first();

        return $mostActive ? $mostActive->jenis_kegiatan : null;
    }
}