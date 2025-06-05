<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\AttendanceRequest;
use Illuminate\Http\Request;
use App\Models\Absen;
use App\Models\Anak;
use App\Models\Aktivitas;
use App\Models\AttendanceVerification;
use App\Services\AttendanceService;
use App\Services\VerificationService;
use App\Http\Resources\AttendanceResource;
use Carbon\Carbon;

class AttendanceController extends Controller
{
    protected $attendanceService;
    protected $verificationService;
    
    public function __construct(
        AttendanceService $attendanceService,
        VerificationService $verificationService
    ) {
        $this->attendanceService = $attendanceService;
        $this->verificationService = $verificationService;
    }
    
    public function recordAttendanceByQr(AttendanceRequest $request)
    {
        try {
            $aktivitas = Aktivitas::findOrFail($request->id_aktivitas);
            $attendanceCheck = $aktivitas->canRecordAttendance();
            
            if (!$attendanceCheck['allowed']) {
                return response()->json([
                    'success' => false,
                    'message' => $attendanceCheck['reason']
                ], 422);
            }
            
            $result = $this->attendanceService->recordAttendanceByQr(
                $request->id_anak,
                $request->id_aktivitas,
                $request->status,
                $request->token,
                $request->arrival_time
            );
            
            if (!$result['success']) {
                if (isset($result['duplicate']) && $result['duplicate']) {
                    return response()->json([
                        'success' => false,
                        'message' => $result['message'],
                        'data' => new AttendanceResource($result['absen'])
                    ], 409);
                }
                
                return response()->json([
                    'success' => false,
                    'message' => $result['message']
                ], 400);
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Attendance recorded successfully',
                'data' => new AttendanceResource($result['absen']),
                'verification' => $result['verification']
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }
    
    public function recordAttendanceManually(AttendanceRequest $request)
    {
        try {
            $aktivitas = Aktivitas::findOrFail($request->id_aktivitas);
            $attendanceCheck = $aktivitas->canRecordAttendance();
            
            if (!$attendanceCheck['allowed']) {
                return response()->json([
                    'success' => false,
                    'message' => $attendanceCheck['reason']
                ], 422);
            }
            
            $result = $this->attendanceService->recordAttendanceManually(
                $request->id_anak,
                $request->id_aktivitas,
                $request->status,
                $request->notes,
                $request->arrival_time
            );
            
            if (!$result['success']) {
                if (isset($result['duplicate']) && $result['duplicate']) {
                    return response()->json([
                        'success' => false,
                        'message' => $result['message'],
                        'data' => new AttendanceResource($result['absen'])
                    ], 409);
                }
                
                return response()->json([
                    'success' => false,
                    'message' => $result['message']
                ], 400);
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Attendance recorded manually',
                'data' => new AttendanceResource($result['absen']),
                'verification' => $result['verification']
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }
    
    public function getByActivity($id_aktivitas, Request $request)
    {
        $filters = $request->only(['is_verified', 'verification_status', 'status']);
        
        try {
            $attendanceRecords = $this->attendanceService->getAttendanceByActivity($id_aktivitas, $filters);
            
            return response()->json([
                'success' => true,
                'data' => AttendanceResource::collection($attendanceRecords)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve attendance records: ' . $e->getMessage()
            ], 500);
        }
    }
    
    public function getByStudent($id_anak, AttendanceRequest $request)
    {
        $filters = $request->only(['is_verified', 'verification_status', 'status', 'date_from', 'date_to']);
        
        try {
            $attendanceRecords = $this->attendanceService->getAttendanceByStudent($id_anak, $filters);
            
            return response()->json([
                'success' => true,
                'data' => AttendanceResource::collection($attendanceRecords)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve attendance records: ' . $e->getMessage()
            ], 500);
        }
    }
    
    public function manualVerify($id_absen, AttendanceRequest $request)
    {
        $result = $this->verificationService->verifyManually($id_absen, $request->notes);
        
        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message']
            ], 400);
        }
        
        return response()->json([
            'success' => true,
            'message' => 'Attendance manually verified',
            'data' => $result['verification']
        ]);
    }
    
    public function rejectVerification($id_absen, AttendanceRequest $request)
    {
        $result = $this->verificationService->rejectVerification($id_absen, $request->reason);
        
        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message']
            ], 400);
        }
        
        return response()->json([
            'success' => true,
            'message' => 'Attendance verification rejected',
            'data' => $result['verification']
        ]);
    }
    
    public function getVerificationHistory($id_absen)
    {
        try {
            $verificationHistory = $this->verificationService->getVerificationHistory($id_absen);
            
            return response()->json([
                'success' => true,
                'data' => $verificationHistory
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve verification history: ' . $e->getMessage()
            ], 500);
        }
    }
}