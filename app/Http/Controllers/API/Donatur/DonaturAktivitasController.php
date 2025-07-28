<?php

namespace App\Http\Controllers\Api\Donatur;

use App\Http\Controllers\Controller;
use App\Models\Aktivitas;
use App\Models\Absen;
use App\Models\Anak;
use App\Models\Donatur;
use Illuminate\Http\Request;

class DonaturAktivitasController extends Controller
{
    /**
     * Get aktivitas list for specific child
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

            // Get aktivitas that have attendance records for this child
            $aktivitasList = Aktivitas::whereHas('absen', function($query) use ($childId) {
                    $query->whereHas('absenUser', function($q) use ($childId) {
                        $q->where('id_anak', $childId);
                    });
                })
                ->where('id_shelter', $child->id_shelter)
                ->with(['shelter', 'materiData'])
                ->orderBy('tanggal', 'desc')
                ->get()
                ->map(function ($aktivitas) use ($childId) {
                    // Get attendance record for this child
                    $attendance = Absen::whereHas('absenUser', function ($query) use ($childId) {
                        $query->where('id_anak', $childId);
                    })
                    ->where('id_aktivitas', $aktivitas->id_aktivitas)
                    ->with('absenUser')
                    ->first();

                    $aktivitas->attendance_status = $attendance ? $attendance->absen : 'Tidak Hadir';
                    $aktivitas->attendance_verified = $attendance ? $attendance->is_verified : false;
                    $aktivitas->attendance_time = $attendance ? $attendance->time_arrived : null;
                    
                    return $aktivitas;
                });

            return response()->json([
                'success' => true,
                'data' => $aktivitasList
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve activities',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get specific aktivitas details
     */
    public function show(Request $request, $childId, $aktivitasId)
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

            // Check if child has attendance for this activity
            $hasAttendance = Absen::whereHas('absenUser', function ($query) use ($childId) {
                    $query->where('id_anak', $childId);
                })
                ->where('id_aktivitas', $aktivitasId)
                ->exists();

            if (!$hasAttendance) {
                return response()->json([
                    'success' => false,
                    'message' => 'Child does not have attendance record for this activity'
                ], 404);
            }

            $aktivitas = Aktivitas::where('id_aktivitas', $aktivitasId)
                ->where('id_shelter', $child->id_shelter)
                ->with(['shelter', 'materiData'])
                ->first();

            if (!$aktivitas) {
                return response()->json([
                    'success' => false,
                    'message' => 'Activity not found'
                ], 404);
            }

            // Get attendance record for this child
            $attendance = Absen::whereHas('absenUser', function ($query) use ($childId) {
                $query->where('id_anak', $childId);
            })
            ->where('id_aktivitas', $aktivitas->id_aktivitas)
            ->with('absenUser')
            ->first();

            $aktivitas->attendance_status = $attendance ? $attendance->absen : 'Tidak Hadir';
            $aktivitas->attendance_verified = $attendance ? $attendance->is_verified : false;
            $aktivitas->attendance_time = $attendance ? $attendance->time_arrived : null;
            $aktivitas->attendance_notes = $attendance ? $attendance->verification_status : null;

            return response()->json([
                'success' => true,
                'data' => $aktivitas
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve activity details',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get attendance summary for child
     */
    public function attendanceSummary(Request $request, $childId)
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

            // Get attendance records for this child
            $attendanceRecords = Absen::whereHas('absenUser', function ($query) use ($childId) {
                $query->where('id_anak', $childId);
            })
            ->whereHas('aktivitas', function ($query) use ($child) {
                $query->where('id_shelter', $child->id_shelter);
            })
            ->get();

            $totalActivitiesAttended = $attendanceRecords->count();
            $presentCount = $attendanceRecords->where('absen', Absen::TEXT_YA)->count();
            $lateCount = $attendanceRecords->where('absen', Absen::TEXT_TERLAMBAT)->count();
            $absentCount = $attendanceRecords->where('absen', Absen::TEXT_TIDAK)->count();

            $summary = [
                'total_activities_attended' => $totalActivitiesAttended,
                'present_count' => $presentCount,
                'late_count' => $lateCount,
                'absent_count' => $absentCount,
                'attendance_percentage' => $totalActivitiesAttended > 0 ? 
                    round((($presentCount + $lateCount) / $totalActivitiesAttended) * 100, 2) : 0,
                'recent_activities' => $this->getRecentActivities($child, $childId)
            ];

            return response()->json([
                'success' => true,
                'data' => $summary
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve attendance summary',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get recent activities with attendance status
     */
    private function getRecentActivities($child, $childId)
    {
        return Aktivitas::whereHas('absen', function($query) use ($childId) {
                $query->whereHas('absenUser', function($q) use ($childId) {
                    $q->where('id_anak', $childId);
                });
            })
            ->where('id_shelter', $child->id_shelter)
            ->with(['shelter', 'materiData'])
            ->orderBy('tanggal', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($aktivitas) use ($childId) {
                $attendance = Absen::whereHas('absenUser', function ($query) use ($childId) {
                    $query->where('id_anak', $childId);
                })
                ->where('id_aktivitas', $aktivitas->id_aktivitas)
                ->first();

                return [
                    'id_aktivitas' => $aktivitas->id_aktivitas,
                    'jenis_kegiatan' => $aktivitas->jenis_kegiatan,
                    'materi' => $aktivitas->materi,
                    'tanggal' => $aktivitas->tanggal,
                    'attendance_status' => $attendance ? $attendance->absen : 'Tidak Hadir'
                ];
            });
    }
}