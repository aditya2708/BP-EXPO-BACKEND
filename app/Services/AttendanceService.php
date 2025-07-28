<?php

namespace App\Services;

use App\Models\Absen;
use App\Models\AbsenUser;
use App\Models\Anak;
use App\Models\Aktivitas;
use App\Models\Tutor;
use App\Models\AttendanceVerification;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class AttendanceService
{
    protected $verificationService;
    
    public function __construct(VerificationService $verificationService)
    {
        $this->verificationService = $verificationService;
    }
    
    protected function checkExistingAttendance($id_anak, $id_aktivitas)
    {
        $absenUser = AbsenUser::where('id_anak', $id_anak)->first();
        
        if (!$absenUser) {
            return false;
        }
        
        $existingRecord = Absen::where('id_absen_user', $absenUser->id_absen_user)
                               ->where('id_aktivitas', $id_aktivitas)
                               ->first();
        
        return $existingRecord ?: false;
    }
    
    protected function determineAttendanceStatus($aktivitas, $arrivalTime, $manualStatus = null)
    {
        if ($manualStatus) {
            switch ($manualStatus) {
                case 'present':
                    return Absen::TEXT_YA;
                case 'absent':
                    return Absen::TEXT_TIDAK;
                case 'late':
                    return Absen::TEXT_TERLAMBAT;
            }
        }
        
        $activityDate = Carbon::parse($aktivitas->tanggal)->startOfDay();
        $currentDate = Carbon::now()->startOfDay();
        
        if ($activityDate->gt($currentDate)) {
            throw new \Exception('Activity has not started yet. Please wait until the activity date.');
        }
        
        if ($activityDate->lt($currentDate)) {
            return Absen::TEXT_TIDAK;
        }
        
        if (!$aktivitas->start_time) {
            return Absen::TEXT_YA;
        }
        
        $comparisonTime = $arrivalTime;
        
        if ($aktivitas->end_time && $aktivitas->isAbsent($comparisonTime)) {
            return Absen::TEXT_TIDAK;
        }
        
        if ($aktivitas->isLate($comparisonTime)) {
            return Absen::TEXT_TERLAMBAT;
        }
        
        return Absen::TEXT_YA;
    }
    
    public function recordAttendanceByQr($id_anak, $id_aktivitas, $status = null, $token, $arrivalTime = null)
    {
        DB::beginTransaction();
        
        try {
            $existingRecord = $this->checkExistingAttendance($id_anak, $id_aktivitas);
            
            if ($existingRecord) {
                DB::rollback();
                return [
                    'success' => false,
                    'message' => 'Attendance record already exists for this student in this activity',
                    'duplicate' => true,
                    'absen' => $existingRecord
                ];
            }
            
            $anak = Anak::findOrFail($id_anak);
            $aktivitas = Aktivitas::findOrFail($id_aktivitas);
            $absenUser = AbsenUser::firstOrCreate(['id_anak' => $id_anak]);
            
            $now = Carbon::now();
            $timeArrived = $arrivalTime ? Carbon::parse($arrivalTime) : $now;
            
            $attendanceStatus = $this->determineAttendanceStatus($aktivitas, $timeArrived, $status);
            
            $absen = Absen::create([
                'absen' => $attendanceStatus,
                'id_absen_user' => $absenUser->id_absen_user,
                'id_aktivitas' => $id_aktivitas,
                'is_read' => false,
                'is_verified' => false,
                'verification_status' => Absen::VERIFICATION_PENDING,
                'time_arrived' => $timeArrived
            ]);
            
            $verificationResult = $this->verificationService->verifyByQrCode(
                $absen->id_absen,
                $token,
                'QR code verification via mobile app'
            );
            
            if ($verificationResult['success']) {
                $absen->is_verified = true;
                $absen->verification_status = Absen::VERIFICATION_VERIFIED;
                $absen->save();
            }
            
            DB::commit();
            
            return [
                'success' => true,
                'absen' => $absen->refresh(),
                'verification' => $verificationResult
            ];
            
        } catch (\Exception $e) {
            DB::rollback();
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    public function recordAttendanceManually($id_anak, $id_aktivitas, $status = null, $notes = '', $arrivalTime = null)
    {
        DB::beginTransaction();
        
        try {
            $existingRecord = $this->checkExistingAttendance($id_anak, $id_aktivitas);
            
            if ($existingRecord) {
                DB::rollback();
                return [
                    'success' => false,
                    'message' => 'Attendance record already exists for this student in this activity',
                    'duplicate' => true,
                    'absen' => $existingRecord
                ];
            }
            
            $anak = Anak::findOrFail($id_anak);
            $aktivitas = Aktivitas::findOrFail($id_aktivitas);
            $absenUser = AbsenUser::firstOrCreate(['id_anak' => $id_anak]);
            
            $now = Carbon::now();
            $timeArrived = $arrivalTime ? Carbon::parse($arrivalTime) : $now;
            
            $attendanceStatus = $this->determineAttendanceStatus($aktivitas, $timeArrived, $status);
            
            $absen = Absen::create([
                'absen' => $attendanceStatus,
                'id_absen_user' => $absenUser->id_absen_user,
                'id_aktivitas' => $id_aktivitas,
                'is_read' => false,
                'is_verified' => true,
                'verification_status' => Absen::VERIFICATION_MANUAL,
                'time_arrived' => $timeArrived
            ]);
            
            $verification = AttendanceVerification::create([
                'id_absen' => $absen->id_absen,
                'verification_method' => AttendanceVerification::METHOD_MANUAL,
                'is_verified' => true,
                'verification_notes' => $notes ?: 'Manual verification by admin',
                'verified_by' => Auth::user()->name ?? 'System',
                'verified_at' => Carbon::now()
            ]);
            
            DB::commit();
            
            return [
                'success' => true,
                'absen' => $absen->refresh(),
                'verification' => $verification
            ];
            
        } catch (\Exception $e) {
            DB::rollback();
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    public function getAttendanceByActivity($id_aktivitas, $filters = [])
    {
        $query = Absen::where('id_aktivitas', $id_aktivitas)
                     ->with([
                         'absenUser.anak',
                         'absenUser.tutor',
                         'aktivitas',
                         'verifications'
                     ]);
        
        if (isset($filters['is_verified'])) {
            $query->where('is_verified', $filters['is_verified']);
        }
        
        if (isset($filters['verification_status'])) {
            $query->where('verification_status', $filters['verification_status']);
        }
        
        if (isset($filters['status'])) {
            if ($filters['status'] === 'present') {
                $query->where('absen', Absen::TEXT_YA);
            } else if ($filters['status'] === 'absent') {
                $query->where('absen', Absen::TEXT_TIDAK);
            } else if ($filters['status'] === 'late') {
                $query->where('absen', Absen::TEXT_TERLAMBAT);
            }
        }
        
        return $query->get();
    }
    
    public function getAttendanceByStudent($id_anak, $filters = [])
    {
        $absenUser = AbsenUser::where('id_anak', $id_anak)->first();
        
        if (!$absenUser) {
            return collect();
        }
        
        $query = Absen::where('id_absen_user', $absenUser->id_absen_user)
                     ->with([
                         'absenUser.anak',
                         'absenUser.tutor',
                         'aktivitas',
                         'verifications'
                     ]);
        
        if (isset($filters['is_verified'])) {
            $query->where('is_verified', $filters['is_verified']);
        }
        
        if (isset($filters['verification_status'])) {
            $query->where('verification_status', $filters['verification_status']);
        }
        
        if (isset($filters['status'])) {
            if ($filters['status'] === 'present') {
                $query->where('absen', Absen::TEXT_YA);
            } else if ($filters['status'] === 'absent') {
                $query->where('absen', Absen::TEXT_TIDAK);
            } else if ($filters['status'] === 'late') {
                $query->where('absen', Absen::TEXT_TERLAMBAT);
            }
        }
        
        if (isset($filters['date_from'])) {
            $query->whereHas('aktivitas', function ($q) use ($filters) {
                $q->where('tanggal', '>=', $filters['date_from']);
            });
        }
        
        if (isset($filters['date_to'])) {
            $query->whereHas('aktivitas', function ($q) use ($filters) {
                $q->where('tanggal', '<=', $filters['date_to']);
            });
        }
        
        return $query->get();
    }
    
    public function generateAttendanceStats($startDate, $endDate, $id_shelter = null)
    {
        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->endOfDay();
        
        $aktivitasQuery = Aktivitas::whereBetween('tanggal', [$start, $end]);
        
        if ($id_shelter) {
            $aktivitasQuery->where('id_shelter', $id_shelter);
        }
        
        $aktivitasIds = $aktivitasQuery->pluck('id_aktivitas');
        
        $attendanceRecords = Absen::whereIn('id_aktivitas', $aktivitasIds)
                               ->with(['aktivitas', 'absenUser.anak'])
                               ->get();
        
        $totalRecords = $attendanceRecords->count();
        $present = $attendanceRecords->where('absen', Absen::TEXT_YA)->count();
        $late = $attendanceRecords->where('absen', Absen::TEXT_TERLAMBAT)->count();
        $absent = $totalRecords - $present - $late;
        $verified = $attendanceRecords->where('is_verified', true)->count();
        $unverified = $totalRecords - $verified;
        
        $verificationMethods = AttendanceVerification::whereIn('id_absen', $attendanceRecords->pluck('id_absen'))
                                                  ->get()
                                                  ->groupBy('verification_method')
                                                  ->map(function ($group) {
                                                      return $group->count();
                                                  });
        
        return [
            'date_range' => [
                'start' => $start->format('Y-m-d'),
                'end' => $end->format('Y-m-d'),
            ],
            'total_records' => $totalRecords,
            'verification_status' => [
                'verified' => $verified,
                'unverified' => $unverified,
                'verification_rate' => $totalRecords > 0 ? round(($verified / $totalRecords) * 100, 2) : 0,
            ],
            'attendance_status' => [
                'present' => $present,
                'late' => $late,
                'absent' => $absent,
                'present_rate' => $totalRecords > 0 ? round(($present / $totalRecords) * 100, 2) : 0,
                'late_rate' => $totalRecords > 0 ? round(($late / $totalRecords) * 100, 2) : 0,
                'attendance_rate' => $totalRecords > 0 ? round((($present + $late) / $totalRecords) * 100, 2) : 0,
            ],
            'verification_methods' => $verificationMethods,
        ];
    }

    public function recordTutorAttendanceByQr($id_tutor, $id_aktivitas, $status = null, $token, $arrivalTime = null)
    {
        DB::beginTransaction();
        
        try {
            $existingRecord = $this->checkExistingTutorAttendance($id_tutor, $id_aktivitas);
            
            if ($existingRecord) {
                DB::rollback();
                return [
                    'success' => false,
                    'message' => 'Attendance record already exists for this tutor in this activity',
                    'duplicate' => true,
                    'absen' => $existingRecord
                ];
            }
            
            $tutor = Tutor::findOrFail($id_tutor);
            $aktivitas = Aktivitas::findOrFail($id_aktivitas);
            
            if ($aktivitas->id_tutor != $id_tutor) {
                return [
                    'success' => false,
                    'message' => 'Tutor is not assigned to this activity'
                ];
            }
            
            $absenUser = AbsenUser::firstOrCreate(['id_tutor' => $id_tutor]);
            
            $now = Carbon::now();
            $timeArrived = $arrivalTime ? Carbon::parse($arrivalTime) : $now;
            
            $attendanceStatus = $this->determineAttendanceStatus($aktivitas, $timeArrived, $status);
            
            $absen = Absen::create([
                'absen' => $attendanceStatus,
                'id_absen_user' => $absenUser->id_absen_user,
                'id_aktivitas' => $id_aktivitas,
                'is_read' => false,
                'is_verified' => false,
                'verification_status' => Absen::VERIFICATION_PENDING,
                'time_arrived' => $timeArrived
            ]);
            
            $verificationResult = $this->verificationService->verifyTutorByQrCode(
                $absen->id_absen,
                $token,
                'QR code verification via mobile app'
            );
            
            if ($verificationResult['success']) {
                $absen->is_verified = true;
                $absen->verification_status = Absen::VERIFICATION_VERIFIED;
                $absen->save();
            }
            
            DB::commit();
            
            return [
                'success' => true,
                'absen' => $absen->refresh(),
                'verification' => $verificationResult
            ];
            
        } catch (\Exception $e) {
            DB::rollback();
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    public function recordTutorAttendanceManually($id_tutor, $id_aktivitas, $status = null, $notes = '', $arrivalTime = null)
    {
        DB::beginTransaction();
        
        try {
            $existingRecord = $this->checkExistingTutorAttendance($id_tutor, $id_aktivitas);
            
            if ($existingRecord) {
                DB::rollback();
                return [
                    'success' => false,
                    'message' => 'Attendance record already exists for this tutor in this activity',
                    'duplicate' => true,
                    'absen' => $existingRecord
                ];
            }
            
            $tutor = Tutor::findOrFail($id_tutor);
            $aktivitas = Aktivitas::findOrFail($id_aktivitas);
            
            if ($aktivitas->id_tutor != $id_tutor) {
                return [
                    'success' => false,
                    'message' => 'Tutor is not assigned to this activity'
                ];
            }
            
            $absenUser = AbsenUser::firstOrCreate(['id_tutor' => $id_tutor]);
            
            $now = Carbon::now();
            $timeArrived = $arrivalTime ? Carbon::parse($arrivalTime) : $now;
            
            $attendanceStatus = $this->determineAttendanceStatus($aktivitas, $timeArrived, $status);
            
            $absen = Absen::create([
                'absen' => $attendanceStatus,
                'id_absen_user' => $absenUser->id_absen_user,
                'id_aktivitas' => $id_aktivitas,
                'is_read' => false,
                'is_verified' => true,
                'verification_status' => Absen::VERIFICATION_MANUAL,
                'time_arrived' => $timeArrived
            ]);
            
            $verification = AttendanceVerification::create([
                'id_absen' => $absen->id_absen,
                'verification_method' => AttendanceVerification::METHOD_MANUAL,
                'is_verified' => true,
                'verification_notes' => $notes ?: 'Manual tutor verification by admin',
                'verified_by' => Auth::user()->name ?? 'System',
                'verified_at' => Carbon::now(),
                'metadata' => [
                    'type' => 'tutor'
                ]
            ]);
            
            DB::commit();
            
            return [
                'success' => true,
                'absen' => $absen->refresh(),
                'verification' => $verification
            ];
            
        } catch (\Exception $e) {
            DB::rollback();
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    public function getTutorAttendanceByActivity($id_aktivitas)
    {
        $aktivitas = Aktivitas::findOrFail($id_aktivitas);
        
        if (!$aktivitas->id_tutor) {
            return null;
        }
        
        $absenUser = AbsenUser::where('id_tutor', $aktivitas->id_tutor)->first();
        
        if (!$absenUser) {
            return null;
        }
        
        return Absen::where('id_absen_user', $absenUser->id_absen_user)
                    ->where('id_aktivitas', $id_aktivitas)
                    ->with([
                        'absenUser.tutor',
                        'aktivitas',
                        'verifications'
                    ])
                    ->first();
    }

    public function getTutorAttendanceByTutor($id_tutor, $filters = [])
    {
        $absenUser = AbsenUser::where('id_tutor', $id_tutor)->first();
        
        if (!$absenUser) {
            return collect();
        }
        
        $query = Absen::where('id_absen_user', $absenUser->id_absen_user)
                     ->with([
                         'absenUser.tutor',
                         'aktivitas',
                         'verifications'
                     ]);
        
        if (isset($filters['is_verified'])) {
            $query->where('is_verified', $filters['is_verified']);
        }
        
        if (isset($filters['verification_status'])) {
            $query->where('verification_status', $filters['verification_status']);
        }
        
        if (isset($filters['status'])) {
            if ($filters['status'] === 'present') {
                $query->where('absen', Absen::TEXT_YA);
            } else if ($filters['status'] === 'absent') {
                $query->where('absen', Absen::TEXT_TIDAK);
            } else if ($filters['status'] === 'late') {
                $query->where('absen', Absen::TEXT_TERLAMBAT);
            }
        }
        
        if (isset($filters['date_from'])) {
            $query->whereHas('aktivitas', function ($q) use ($filters) {
                $q->where('tanggal', '>=', $filters['date_from']);
            });
        }
        
        if (isset($filters['date_to'])) {
            $query->whereHas('aktivitas', function ($q) use ($filters) {
                $q->where('tanggal', '<=', $filters['date_to']);
            });
        }
        
        return $query->orderBy('created_at', 'desc')->get();
    }

    protected function checkExistingTutorAttendance($id_tutor, $id_aktivitas)
    {
        $absenUser = AbsenUser::where('id_tutor', $id_tutor)->first();
        
        if (!$absenUser) {
            return false;
        }
        
        $existingRecord = Absen::where('id_absen_user', $absenUser->id_absen_user)
                               ->where('id_aktivitas', $id_aktivitas)
                               ->first();
        
        return $existingRecord ?: false;
    }

    public function getTutorActivitiesForHonor($id_tutor, $month, $year)
    {
        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = Carbon::create($year, $month, 1)->endOfMonth();

        return Aktivitas::where('id_tutor', $id_tutor)
            ->whereBetween('tanggal', [$startDate, $endDate])
            ->whereHas('absen.absenUser', function($query) use ($id_tutor) {
                $query->where('id_tutor', $id_tutor)
                      ->whereHas('absen', function($subQuery) {
                          $subQuery->whereIn('absen', ['Ya', 'Terlambat']);
                      });
            })
            ->with(['absen.absenUser'])
            ->get();
    }

    public function getStudentAttendanceCount($id_aktivitas)
    {
        return Absen::where('id_aktivitas', $id_aktivitas)
            ->whereIn('absen', ['Ya', 'Terlambat'])
            ->whereHas('absenUser', function($query) {
                $query->whereNotNull('id_anak');
            })
            ->count();
    }

    public function getTutorAttendanceStats($id_tutor, $startDate, $endDate)
    {
        $absenUser = AbsenUser::where('id_tutor', $id_tutor)->first();
        
        if (!$absenUser) {
            return [
                'total_activities' => 0,
                'attended_activities' => 0,
                'attendance_rate' => 0
            ];
        }

        $totalActivities = Aktivitas::where('id_tutor', $id_tutor)
            ->whereBetween('tanggal', [$startDate, $endDate])
            ->count();

        $attendedActivities = Absen::where('id_absen_user', $absenUser->id_absen_user)
            ->whereIn('absen', ['Ya', 'Terlambat'])
            ->whereHas('aktivitas', function($query) use ($startDate, $endDate) {
                $query->whereBetween('tanggal', [$startDate, $endDate]);
            })
            ->count();

        return [
            'total_activities' => $totalActivities,
            'attended_activities' => $attendedActivities,
            'attendance_rate' => $totalActivities > 0 ? round(($attendedActivities / $totalActivities) * 100, 2) : 0
        ];
    }
}