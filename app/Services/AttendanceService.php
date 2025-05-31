<?php

namespace App\Services;

use App\Models\Absen;
use App\Models\AbsenUser;
use App\Models\Anak;
use App\Models\Aktivitas;
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
    
    /**
     * Check if attendance record already exists for student in activity
     * 
     * @param int $id_anak Student ID
     * @param int $id_aktivitas Activity ID
     * @return bool|Absen Returns false if no record exists, otherwise returns the existing record
     */
    protected function checkExistingAttendance($id_anak, $id_aktivitas)
    {
        // Get the absen_user for this student
        $absenUser = AbsenUser::where('id_anak', $id_anak)->first();
        
        if (!$absenUser) {
            return false;
        }
        
        // Check if attendance record already exists
        $existingRecord = Absen::where('id_absen_user', $absenUser->id_absen_user)
                               ->where('id_aktivitas', $id_aktivitas)
                               ->first();
        
        return $existingRecord ?: false;
    }
    
 /**
 * Determine attendance status based on arrival time and activity schedule
 * 
 * @param Aktivitas $aktivitas Activity to check
 * @param Carbon $arrivalTime Time of arrival
 * @param string $manualStatus Optional manual override status
 * @return string The determined attendance status
 */
protected function determineAttendanceStatus($aktivitas, $arrivalTime, $manualStatus = null)
{
    // If manual status is provided and we're not enforcing automatic detection, use it
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
    
    // Check if activity has schedule information
    if (!$aktivitas->start_time) {
        // No schedule info, use a default status
        return Absen::TEXT_YA;
    }
    
    // For same-day activities, use the actual arrival time
    // For different-day activities, only compare the time component
    $activityDate = Carbon::parse($aktivitas->tanggal)->format('Y-m-d');
    $arrivalDate = $arrivalTime->format('Y-m-d');
    
    if ($activityDate !== $arrivalDate) {
        // Different day - create a comparison time using activity date with arrival time
        $comparisonTime = Carbon::parse($activityDate . ' ' . $arrivalTime->format('H:i:s'));
    } else {
        // Same day - use actual arrival time
        $comparisonTime = $arrivalTime;
    }
    
    // Check if student is absent (after end time)
    if ($aktivitas->end_time && $aktivitas->isAbsent($comparisonTime)) {
        return Absen::TEXT_TIDAK;
    }
    
    // Check if student is late
    if ($aktivitas->isLate($comparisonTime)) {
        return Absen::TEXT_TERLAMBAT;
    }
    
    // Otherwise, student is present
    return Absen::TEXT_YA;
}
    
    /**
     * Record attendance using QR code verification
     * 
     * @param int $id_anak Student ID
     * @param int $id_aktivitas Activity ID
     * @param string $status Attendance status (present/absent/late) or null for auto-detection
     * @param string $token QR token used for verification
     * @param string|null $arrivalTime Optional manual arrival time override
     * @return array Result with attendance record
     */
    public function recordAttendanceByQr($id_anak, $id_aktivitas, $status = null, $token, $arrivalTime = null)
    {
        // Begin transaction
        DB::beginTransaction();
        
        try {
            // Check for existing attendance record
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
            
            // Validate student exists
            $anak = Anak::findOrFail($id_anak);
            
            // Validate activity exists
            $aktivitas = Aktivitas::findOrFail($id_aktivitas);
            
            // Create or get AbsenUser record
            $absenUser = AbsenUser::firstOrCreate(['id_anak' => $id_anak]);
            
            // Determine arrival time
            $now = Carbon::now();
            $timeArrived = $arrivalTime ? Carbon::parse($arrivalTime) : $now;
            
            // Determine attendance status based on activity schedule and arrival time
            $attendanceStatus = $this->determineAttendanceStatus($aktivitas, $timeArrived, $status);
            
            // Create attendance record
            $absen = Absen::create([
                'absen' => $attendanceStatus,
                'id_absen_user' => $absenUser->id_absen_user,
                'id_aktivitas' => $id_aktivitas,
                'is_read' => false,
                'is_verified' => false,
                'verification_status' => Absen::VERIFICATION_PENDING,
                'time_arrived' => $timeArrived
            ]);
            
            // Process verification
            $verificationResult = $this->verificationService->verifyByQrCode(
                $absen->id_absen,
                $token,
                'QR code verification via mobile app'
            );
            
            // Update attendance record based on verification result
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
    
    /**
     * Record attendance manually (by admin)
     * 
     * @param int $id_anak Student ID
     * @param int $id_aktivitas Activity ID
     * @param string $status Attendance status (present/absent/late) or null for auto-detection
     * @param string $notes Verification notes
     * @param string|null $arrivalTime Optional manual arrival time
     * @return array Result with attendance record
     */
    public function recordAttendanceManually($id_anak, $id_aktivitas, $status = null, $notes = '', $arrivalTime = null)
    {
        // Begin transaction
        DB::beginTransaction();
        
        try {
            // Check for existing attendance record
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
            
            // Validate student exists
            $anak = Anak::findOrFail($id_anak);
            
            // Validate activity exists
            $aktivitas = Aktivitas::findOrFail($id_aktivitas);
            
            // Create or get AbsenUser record
            $absenUser = AbsenUser::firstOrCreate(['id_anak' => $id_anak]);
            
            // Determine arrival time
            $now = Carbon::now();
            $timeArrived = $arrivalTime ? Carbon::parse($arrivalTime) : $now;
            
            // Determine attendance status based on activity schedule and arrival time
            $attendanceStatus = $this->determineAttendanceStatus($aktivitas, $timeArrived, $status);
            
            // Create attendance record with manual verification status
            $absen = Absen::create([
                'absen' => $attendanceStatus,
                'id_absen_user' => $absenUser->id_absen_user,
                'id_aktivitas' => $id_aktivitas,
                'is_read' => false,
                'is_verified' => true,
                'verification_status' => Absen::VERIFICATION_MANUAL,
                'time_arrived' => $timeArrived
            ]);
            
            // Create manual verification record
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
    
    /**
     * Get attendance records for an activity
     * 
     * @param int $id_aktivitas Activity ID
     * @param array $filters Optional filters
     * @return \Illuminate\Database\Eloquent\Collection Attendance records
     */
    public function getAttendanceByActivity($id_aktivitas, $filters = [])
    {
        $query = Absen::where('id_aktivitas', $id_aktivitas)
                     ->with([
                         'absenUser.anak',
                         'absenUser.tutor',
                         'aktivitas',
                         'verifications'
                     ]);
        
        // Apply filters
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
    
    /**
     * Get attendance records for a student
     * 
     * @param int $id_anak Student ID
     * @param array $filters Optional filters
     * @return \Illuminate\Database\Eloquent\Collection Attendance records
     */
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
        
        // Apply filters
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
    
    /**
     * Generate attendance statistics report for a date range
     * 
     * @param string $startDate Start date (Y-m-d)
     * @param string $endDate End date (Y-m-d)
     * @param int|null $id_shelter Optional shelter ID filter
     * @return array Attendance statistics
     */
    public function generateAttendanceStats($startDate, $endDate, $id_shelter = null)
    {
        // Convert dates to Carbon instances
        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->endOfDay();
        
        // Base query to get activities in the date range
        $aktivitasQuery = Aktivitas::whereBetween('tanggal', [$start, $end]);
        
        // Apply shelter filter if provided
        if ($id_shelter) {
            $aktivitasQuery->where('id_shelter', $id_shelter);
        }
        
        // Get activities
        $aktivitasIds = $aktivitasQuery->pluck('id_aktivitas');
        
        // Get attendance records for these activities
        $attendanceRecords = Absen::whereIn('id_aktivitas', $aktivitasIds)
                               ->with(['aktivitas', 'absenUser.anak'])
                               ->get();
        
        // Calculate statistics
        $totalRecords = $attendanceRecords->count();
        $present = $attendanceRecords->where('absen', Absen::TEXT_YA)->count();
        $late = $attendanceRecords->where('absen', Absen::TEXT_TERLAMBAT)->count();
        $absent = $totalRecords - $present - $late;
        $verified = $attendanceRecords->where('is_verified', true)->count();
        $unverified = $totalRecords - $verified;
        
        // Calculate rates
        $presentRate = $totalRecords > 0 ? (($present / $totalRecords) * 100) : 0;
        $lateRate = $totalRecords > 0 ? (($late / $totalRecords) * 100) : 0;
        $attendanceRate = $totalRecords > 0 ? ((($present + $late) / $totalRecords) * 100) : 0;
        $verificationRate = $totalRecords > 0 ? (($verified / $totalRecords) * 100) : 0;
        
        // Group by verification method if verifications are loaded
        $verificationMethods = AttendanceVerification::whereIn('id_absen', $attendanceRecords->pluck('id_absen'))
                                                  ->get()
                                                  ->groupBy('verification_method')
                                                  ->map(function ($group) {
                                                      return $group->count();
                                                  });
        
        // Return compiled statistics
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

    // Add these methods to the existing AttendanceService.php class

/**
 * Record tutor attendance using QR code verification
 * 
 * @param int $id_tutor Tutor ID
 * @param int $id_aktivitas Activity ID
 * @param string $status Attendance status or null for auto-detection
 * @param string $token QR token used for verification
 * @param string|null $arrivalTime Optional manual arrival time override
 * @return array Result with attendance record
 */
public function recordTutorAttendanceByQr($id_tutor, $id_aktivitas, $status = null, $token, $arrivalTime = null)
{
    DB::beginTransaction();
    
    try {
        // Check for existing attendance record
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
        
        // Validate tutor exists
        $tutor = Tutor::findOrFail($id_tutor);
        
        // Validate activity exists and tutor is assigned
        $aktivitas = Aktivitas::findOrFail($id_aktivitas);
        
        if ($aktivitas->id_tutor != $id_tutor) {
            return [
                'success' => false,
                'message' => 'Tutor is not assigned to this activity'
            ];
        }
        
        // Create or get AbsenUser record for tutor
        $absenUser = AbsenUser::firstOrCreate(['id_tutor' => $id_tutor]);
        
        // Determine arrival time
        $now = Carbon::now();
        $timeArrived = $arrivalTime ? Carbon::parse($arrivalTime) : $now;
        
        // Determine attendance status
        $attendanceStatus = $this->determineAttendanceStatus($aktivitas, $timeArrived, $status);
        
        // Create attendance record
        $absen = Absen::create([
            'absen' => $attendanceStatus,
            'id_absen_user' => $absenUser->id_absen_user,
            'id_aktivitas' => $id_aktivitas,
            'is_read' => false,
            'is_verified' => false,
            'verification_status' => Absen::VERIFICATION_PENDING,
            'time_arrived' => $timeArrived
        ]);
        
        // Process verification using tutor token
        $verificationResult = $this->verificationService->verifyTutorByQrCode(
            $absen->id_absen,
            $token,
            'QR code verification via mobile app'
        );
        
        // Update attendance record based on verification result
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

/**
 * Record tutor attendance manually (by admin)
 * 
 * @param int $id_tutor Tutor ID
 * @param int $id_aktivitas Activity ID
 * @param string $status Attendance status or null for auto-detection
 * @param string $notes Verification notes
 * @param string|null $arrivalTime Optional manual arrival time
 * @return array Result with attendance record
 */
public function recordTutorAttendanceManually($id_tutor, $id_aktivitas, $status = null, $notes = '', $arrivalTime = null)
{
    DB::beginTransaction();
    
    try {
        // Check for existing attendance record
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
        
        // Validate tutor exists
        $tutor = Tutor::findOrFail($id_tutor);
        
        // Validate activity exists and tutor is assigned
        $aktivitas = Aktivitas::findOrFail($id_aktivitas);
        
        if ($aktivitas->id_tutor != $id_tutor) {
            return [
                'success' => false,
                'message' => 'Tutor is not assigned to this activity'
            ];
        }
        
        // Create or get AbsenUser record for tutor
        $absenUser = AbsenUser::firstOrCreate(['id_tutor' => $id_tutor]);
        
        // Determine arrival time
        $now = Carbon::now();
        $timeArrived = $arrivalTime ? Carbon::parse($arrivalTime) : $now;
        
        // Determine attendance status
        $attendanceStatus = $this->determineAttendanceStatus($aktivitas, $timeArrived, $status);
        
        // Create attendance record with manual verification status
        $absen = Absen::create([
            'absen' => $attendanceStatus,
            'id_absen_user' => $absenUser->id_absen_user,
            'id_aktivitas' => $id_aktivitas,
            'is_read' => false,
            'is_verified' => true,
            'verification_status' => Absen::VERIFICATION_MANUAL,
            'time_arrived' => $timeArrived
        ]);
        
        // Create manual verification record
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

/**
 * Get tutor attendance record for an activity
 * 
 * @param int $id_aktivitas Activity ID
 * @return mixed Tutor attendance record or null
 */
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

/**
 * Get attendance records for a tutor
 * 
 * @param int $id_tutor Tutor ID
 * @param array $filters Optional filters
 * @return \Illuminate\Database\Eloquent\Collection Attendance records
 */
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
    
    // Apply filters
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

/**
 * Check if tutor attendance record already exists for activity
 * 
 * @param int $id_tutor Tutor ID
 * @param int $id_aktivitas Activity ID
 * @return bool|Absen Returns false if no record exists, otherwise returns the existing record
 */
protected function checkExistingTutorAttendance($id_tutor, $id_aktivitas)
{
    // Get the absen_user for this tutor
    $absenUser = AbsenUser::where('id_tutor', $id_tutor)->first();
    
    if (!$absenUser) {
        return false;
    }
    
    // Check if attendance record already exists
    $existingRecord = Absen::where('id_absen_user', $absenUser->id_absen_user)
                           ->where('id_aktivitas', $id_aktivitas)
                           ->first();
    
    return $existingRecord ?: false;
}
}