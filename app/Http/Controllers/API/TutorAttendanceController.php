<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Tutor;
use App\Models\Aktivitas;
use App\Models\Absen;
use App\Models\AbsenUser;
use App\Services\AttendanceService;
use App\Services\QrTokenService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class TutorAttendanceController extends Controller
{
    protected $attendanceService;
    protected $qrTokenService;
    
    public function __construct(
        AttendanceService $attendanceService,
        QrTokenService $qrTokenService
    ) {
        $this->attendanceService = $attendanceService;
        $this->qrTokenService = $qrTokenService;
    }
    
    public function generateTutorToken(Request $request)
    {
        $request->validate([
            'id_tutor' => 'required|exists:tutor,id_tutor',
            'valid_days' => 'nullable|integer|min:1|max:365'
        ]);
        
        try {
            $validDays = $request->input('valid_days', 30);
            $token = $this->qrTokenService->generateTutorToken($request->id_tutor, $validDays);
            
            return response()->json([
                'success' => true,
                'message' => 'QR token generated successfully for tutor',
                'data' => $token->load('tutor')
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate tutor token: ' . $e->getMessage()
            ], 500);
        }
    }
    
    public function validateTutorToken(Request $request)
    {
        $request->validate([
            'token' => 'required|string'
        ]);
        
        $result = $this->qrTokenService->validateTutorToken($request->token);
        
        if (!$result['valid']) {
            return response()->json([
                'success' => false,
                'message' => $result['message']
            ], 400);
        }
        
        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'data' => [
                'tutor' => $result['tutor'],
                'token' => $result['token']
            ]
        ]);
    }
    
    public function recordTutorAttendanceByQr(Request $request)
    {
        $request->validate([
            'id_aktivitas' => 'required|exists:aktivitas,id_aktivitas',
            'token' => 'required|string',
            'arrival_time' => 'nullable|date_format:Y-m-d H:i:s'
        ]);
        
        DB::beginTransaction();
        
        try {
            $tokenValidation = $this->qrTokenService->validateTutorToken($request->token);
            
            if (!$tokenValidation['valid']) {
                return response()->json([
                    'success' => false,
                    'message' => $tokenValidation['message']
                ], 400);
            }
            
            $tutor = $tokenValidation['tutor'];
            $aktivitas = Aktivitas::findOrFail($request->id_aktivitas);
            
            if ($aktivitas->id_tutor != $tutor->id_tutor) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tutor is not assigned to this activity'
                ], 403);
            }
            
            $absenUser = AbsenUser::where('id_tutor', $tutor->id_tutor)->first();
            
            if (!$absenUser) {
                $absenUser = AbsenUser::create(['id_tutor' => $tutor->id_tutor]);
            }
            
            $existingAttendance = Absen::where('id_absen_user', $absenUser->id_absen_user)
                                      ->where('id_aktivitas', $request->id_aktivitas)
                                      ->first();
            
            if ($existingAttendance) {
                DB::rollback();
                return response()->json([
                    'success' => false,
                    'message' => 'Attendance already recorded for this tutor in this activity',
                    'data' => $existingAttendance
                ], 409);
            }
            
            $arrivalTime = $request->arrival_time ? Carbon::parse($request->arrival_time) : Carbon::now();
            $attendanceStatus = $this->determineAttendanceStatus($aktivitas, $arrivalTime);
            
            $absen = Absen::create([
                'absen' => $attendanceStatus,
                'id_absen_user' => $absenUser->id_absen_user,
                'id_aktivitas' => $request->id_aktivitas,
                'is_read' => false,
                'is_verified' => true,
                'verification_status' => 'verified',
                'time_arrived' => $arrivalTime
            ]);
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Tutor attendance recorded successfully',
                'data' => $absen->load(['absenUser.tutor', 'aktivitas'])
            ]);
            
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Failed to record attendance: ' . $e->getMessage()
            ], 500);
        }
    }
    
    public function recordTutorAttendanceManually(Request $request)
    {
        $request->validate([
            'id_tutor' => 'required|exists:tutor,id_tutor',
            'id_aktivitas' => 'required|exists:aktivitas,id_aktivitas',
            'status' => 'nullable|in:present,absent,late',
            'notes' => 'required|string|max:255',
            'arrival_time' => 'nullable|date_format:Y-m-d H:i:s'
        ]);
        
        DB::beginTransaction();
        
        try {
            $aktivitas = Aktivitas::findOrFail($request->id_aktivitas);
            
            if ($aktivitas->id_tutor != $request->id_tutor) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tutor is not assigned to this activity'
                ], 403);
            }
            
            $absenUser = AbsenUser::where('id_tutor', $request->id_tutor)->first();
            
            if (!$absenUser) {
                $absenUser = AbsenUser::create(['id_tutor' => $request->id_tutor]);
            }
            
            $existingAttendance = Absen::where('id_absen_user', $absenUser->id_absen_user)
                                      ->where('id_aktivitas', $request->id_aktivitas)
                                      ->first();
            
            if ($existingAttendance) {
                DB::rollback();
                return response()->json([
                    'success' => false,
                    'message' => 'Attendance already recorded for this tutor in this activity',
                    'data' => $existingAttendance
                ], 409);
            }
            
            $arrivalTime = $request->arrival_time ? Carbon::parse($request->arrival_time) : Carbon::now();
            $attendanceStatus = $this->determineAttendanceStatus($aktivitas, $arrivalTime, $request->status);
            
            $absen = Absen::create([
                'absen' => $attendanceStatus,
                'id_absen_user' => $absenUser->id_absen_user,
                'id_aktivitas' => $request->id_aktivitas,
                'is_read' => false,
                'is_verified' => true,
                'verification_status' => 'manual',
                'time_arrived' => $arrivalTime
            ]);
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Tutor attendance recorded manually',
                'data' => $absen->load(['absenUser.tutor', 'aktivitas'])
            ]);
            
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Failed to record attendance: ' . $e->getMessage()
            ], 500);
        }
    }
    
    public function getTutorAttendanceByActivity($id_aktivitas)
    {
        try {
            $aktivitas = Aktivitas::with('tutor')->findOrFail($id_aktivitas);
            
            if (!$aktivitas->id_tutor) {
                return response()->json([
                    'success' => true,
                    'message' => 'No tutor assigned to this activity',
                    'data' => null
                ]);
            }
            
            $absenUser = AbsenUser::where('id_tutor', $aktivitas->id_tutor)->first();
            
            if (!$absenUser) {
                return response()->json([
                    'success' => true,
                    'message' => 'No attendance record found for the tutor',
                    'data' => null
                ]);
            }
            
            $attendance = Absen::where('id_absen_user', $absenUser->id_absen_user)
                              ->where('id_aktivitas', $id_aktivitas)
                              ->with(['absenUser.tutor', 'aktivitas'])
                              ->first();
            
            return response()->json([
                'success' => true,
                'data' => $attendance
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve tutor attendance: ' . $e->getMessage()
            ], 500);
        }
    }
    
    public function getTutorAttendanceHistory($id_tutor, Request $request)
    {
        $request->validate([
            'date_from' => 'nullable|date_format:Y-m-d',
            'date_to' => 'nullable|date_format:Y-m-d|after_or_equal:date_from',
            'status' => 'nullable|in:present,absent,late'
        ]);
        
        try {
            $tutor = Tutor::findOrFail($id_tutor);
            $absenUser = AbsenUser::where('id_tutor', $id_tutor)->first();
            
            if (!$absenUser) {
                return response()->json([
                    'success' => true,
                    'data' => []
                ]);
            }
            
            $query = Absen::where('id_absen_user', $absenUser->id_absen_user)
                         ->with(['aktivitas', 'absenUser.tutor']);
            
            if ($request->date_from) {
                $query->whereHas('aktivitas', function ($q) use ($request) {
                    $q->whereDate('tanggal', '>=', $request->date_from);
                });
            }
            
            if ($request->date_to) {
                $query->whereHas('aktivitas', function ($q) use ($request) {
                    $q->whereDate('tanggal', '<=', $request->date_to);
                });
            }
            
            if ($request->status) {
                $statusMap = [
                    'present' => Absen::TEXT_YA,
                    'absent' => Absen::TEXT_TIDAK,
                    'late' => Absen::TEXT_TERLAMBAT
                ];
                $query->where('absen', $statusMap[$request->status]);
            }
            
            $attendance = $query->orderBy('created_at', 'desc')->get();
            
            return response()->json([
                'success' => true,
                'data' => $attendance
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve attendance history: ' . $e->getMessage()
            ], 500);
        }
    }
    
    private function determineAttendanceStatus($aktivitas, $arrivalTime, $manualStatus = null)
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
        
        if (!$aktivitas->start_time) {
            return Absen::TEXT_YA;
        }
        
        $activityDate = Carbon::parse($aktivitas->tanggal)->format('Y-m-d');
        $comparisonTime = Carbon::parse($activityDate . ' ' . $arrivalTime->format('H:i:s'));
        
        if ($aktivitas->end_time && $aktivitas->isAbsent($comparisonTime)) {
            return Absen::TEXT_TIDAK;
        }
        
        if ($aktivitas->isLate($comparisonTime)) {
            return Absen::TEXT_TERLAMBAT;
        }
        
        return Absen::TEXT_YA;
    }
}