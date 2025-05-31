<?php

namespace App\Services;

use App\Models\QrToken;
use App\Models\Absen;
use App\Models\AttendanceVerification;
use App\Models\Anak;
use App\Models\Tutor;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class VerificationService
{
    protected $qrTokenService;
    
    public function __construct(QrTokenService $qrTokenService)
    {
        $this->qrTokenService = $qrTokenService;
    }
    
    /**
     * Verify attendance using QR code
     * 
     * @param int $id_absen Attendance ID
     * @param string $token QR code token
     * @param string $notes Optional verification notes
     * @return array Verification result
     */
    public function verifyByQrCode($id_absen, $token, $notes = '')
    {
        // Validate token
        $tokenValidation = $this->qrTokenService->validateToken($token);
        
        if (!$tokenValidation['valid']) {
            return [
                'success' => false,
                'message' => $tokenValidation['message'],
                'verification' => null
            ];
        }
        
        // Get attendance record
        $absen = Absen::find($id_absen);
        if (!$absen) {
            return [
                'success' => false,
                'message' => 'Attendance record not found',
                'verification' => null
            ];
        }
        
        // Verify that token matches the student in attendance record
        $absenUser = $absen->absenUser()->first();
        if (!$absenUser || $absenUser->id_anak != $tokenValidation['anak']->id_anak) {
            return [
                'success' => false,
                'message' => 'QR token does not match the student in attendance record',
                'verification' => null
            ];
        }
        
        // Create verification record
        $verification = AttendanceVerification::create([
            'id_absen' => $id_absen,
            'verification_method' => AttendanceVerification::METHOD_QR,
            'is_verified' => true,
            'verification_notes' => $notes ?: 'Verified by QR code',
            'verified_by' => Auth::user()->name ?? 'System',
            'verified_at' => Carbon::now(),
            'metadata' => [
                'token_id' => $tokenValidation['token']->id_qr_token,
                'device_info' => request()->header('User-Agent')
            ]
        ]);
        
        return [
            'success' => true,
            'message' => 'Attendance successfully verified by QR code',
            'verification' => $verification
        ];
    }
    
    /**
     * Verify tutor attendance using QR code
     * 
     * @param int $id_absen Attendance ID
     * @param string $token QR code token
     * @param string $notes Optional verification notes
     * @return array Verification result
     */
    public function verifyTutorByQrCode($id_absen, $token, $notes = '')
    {
        // Validate token
        $tokenValidation = $this->qrTokenService->validateTutorToken($token);
        
        if (!$tokenValidation['valid']) {
            return [
                'success' => false,
                'message' => $tokenValidation['message'],
                'verification' => null
            ];
        }
        
        // Get attendance record
        $absen = Absen::find($id_absen);
        if (!$absen) {
            return [
                'success' => false,
                'message' => 'Attendance record not found',
                'verification' => null
            ];
        }
        
        // Verify that token matches the tutor in attendance record
        $absenUser = $absen->absenUser()->first();
        if (!$absenUser || $absenUser->id_tutor != $tokenValidation['tutor']->id_tutor) {
            return [
                'success' => false,
                'message' => 'QR token does not match the tutor in attendance record',
                'verification' => null
            ];
        }
        
        // Create verification record
        $verification = AttendanceVerification::create([
            'id_absen' => $id_absen,
            'verification_method' => AttendanceVerification::METHOD_QR,
            'is_verified' => true,
            'verification_notes' => $notes ?: 'Verified by QR code',
            'verified_by' => Auth::user()->name ?? 'System',
            'verified_at' => Carbon::now(),
            'metadata' => [
                'token_id' => $tokenValidation['token']->id_qr_token,
                'device_info' => request()->header('User-Agent'),
                'type' => 'tutor'
            ]
        ]);
        
        return [
            'success' => true,
            'message' => 'Tutor attendance successfully verified by QR code',
            'verification' => $verification
        ];
    }
    
    /**
     * Manual verification by admin/staff
     * 
     * @param int $id_absen Attendance ID
     * @param string $notes Verification notes explaining why manual verification was used
     * @return array Verification result
     */
    public function verifyManually($id_absen, $notes)
    {
        // Check if attendance record exists
        $absen = Absen::find($id_absen);
        if (!$absen) {
            return [
                'success' => false,
                'message' => 'Attendance record not found',
                'verification' => null
            ];
        }
        
        // Determine type (student or tutor)
        $absenUser = $absen->absenUser()->first();
        $type = 'unknown';
        if ($absenUser) {
            if ($absenUser->id_anak) {
                $type = 'student';
            } elseif ($absenUser->id_tutor) {
                $type = 'tutor';
            }
        }
        
        // Create verification record
        $verification = AttendanceVerification::create([
            'id_absen' => $id_absen,
            'verification_method' => AttendanceVerification::METHOD_MANUAL,
            'is_verified' => true,
            'verification_notes' => $notes,
            'verified_by' => Auth::user()->name ?? 'System Admin',
            'verified_at' => Carbon::now(),
            'metadata' => [
                'admin_id' => Auth::id() ?? null,
                'verification_type' => 'manual_override',
                'type' => $type
            ]
        ]);
        
        // Update attendance record
        $absen->is_verified = true;
        $absen->verification_status = Absen::VERIFICATION_MANUAL;
        $absen->save();
        
        return [
            'success' => true,
            'message' => 'Attendance manually verified by admin',
            'verification' => $verification
        ];
    }
    
    /**
     * Reject attendance verification
     * 
     * @param int $id_absen Attendance ID
     * @param string $reason Reason for rejection
     * @return array Rejection result
     */
    public function rejectVerification($id_absen, $reason)
    {
        // Check if attendance record exists
        $absen = Absen::find($id_absen);
        if (!$absen) {
            return [
                'success' => false,
                'message' => 'Attendance record not found',
                'verification' => null
            ];
        }
        
        // Determine type (student or tutor)
        $absenUser = $absen->absenUser()->first();
        $type = 'unknown';
        if ($absenUser) {
            if ($absenUser->id_anak) {
                $type = 'student';
            } elseif ($absenUser->id_tutor) {
                $type = 'tutor';
            }
        }
        
        // Create rejection record
        $verification = AttendanceVerification::create([
            'id_absen' => $id_absen,
            'verification_method' => AttendanceVerification::METHOD_MANUAL,
            'is_verified' => false,
            'verification_notes' => $reason,
            'verified_by' => Auth::user()->name ?? 'System Admin',
            'verified_at' => Carbon::now(),
            'metadata' => [
                'admin_id' => Auth::id() ?? null,
                'verification_type' => 'rejection',
                'type' => $type
            ]
        ]);
        
        // Update attendance record
        $absen->is_verified = false;
        $absen->verification_status = Absen::VERIFICATION_REJECTED;
        $absen->save();
        
        return [
            'success' => true,
            'message' => 'Attendance verification rejected',
            'verification' => $verification
        ];
    }
    
    /**
     * Get verification history for an attendance record
     * 
     * @param int $id_absen Attendance ID
     * @return \Illuminate\Database\Eloquent\Collection Verification records
     */
    public function getVerificationHistory($id_absen)
    {
        return AttendanceVerification::where('id_absen', $id_absen)
                                  ->orderBy('verified_at', 'desc')
                                  ->get();
    }
    
    /**
     * Check if an attendance record is verified
     * 
     * @param int $id_absen Attendance ID
     * @return bool Whether attendance is verified
     */
    public function isVerified($id_absen)
    {
        $absen = Absen::find($id_absen);
        return $absen && $absen->is_verified;
    }
}