<?php

namespace App\Services;

use App\Models\QrToken;
use App\Models\Anak;
use App\Models\Tutor;
use Carbon\Carbon;
use Illuminate\Support\Str;

class QrTokenService
{
    public function generateToken($id_anak, $validDays = 30)
    {
        $anak = Anak::find($id_anak);
        if (!$anak) {
            throw new \Exception("Student not found with ID: {$id_anak}");
        }
        
        QrToken::where('id_anak', $id_anak)
               ->where('is_active', true)
               ->update(['is_active' => false]);
        
        $token = $this->createSecureToken('anak', $id_anak);
        $validUntil = Carbon::now()->addDays($validDays);
        
        return QrToken::create([
            'id_anak' => $id_anak,
            'id_tutor' => null,
            'token' => $token,
            'type' => 'anak',
            'valid_until' => $validUntil,
            'is_active' => true
        ]);
    }
    
    public function generateTutorToken($id_tutor, $validDays = 30)
    {
        $tutor = Tutor::find($id_tutor);
        if (!$tutor) {
            throw new \Exception("Tutor not found with ID: {$id_tutor}");
        }
        
        QrToken::where('id_tutor', $id_tutor)
               ->where('is_active', true)
               ->update(['is_active' => false]);
        
        $token = $this->createSecureToken('tutor', $id_tutor);
        $validUntil = Carbon::now()->addDays($validDays);
        
        return QrToken::create([
            'id_anak' => null,
            'id_tutor' => $id_tutor,
            'token' => $token,
            'type' => 'tutor',
            'valid_until' => $validUntil,
            'is_active' => true
        ]);
    }
    
    public function validateToken($token)
    {
        $qrToken = QrToken::where('token', $token)
                          ->where('type', 'anak')
                          ->with('anak')
                          ->first();
        
        if (!$qrToken) {
            return [
                'valid' => false,
                'message' => 'Token not found',
                'anak' => null
            ];
        }
        
        if (!$qrToken->is_active) {
            return [
                'valid' => false,
                'message' => 'Token is inactive',
                'anak' => null
            ];
        }
        
        if ($qrToken->valid_until && $qrToken->valid_until < Carbon::now()) {
            return [
                'valid' => false,
                'message' => 'Token has expired',
                'anak' => null
            ];
        }
        
        return [
            'valid' => true,
            'message' => 'Token is valid',
            'anak' => $qrToken->anak,
            'token' => $qrToken
        ];
    }
    
    public function validateTutorToken($token)
    {
        $qrToken = QrToken::where('token', $token)
                          ->where('type', 'tutor')
                          ->with('tutor')
                          ->first();
        
        if (!$qrToken) {
            return [
                'valid' => false,
                'message' => 'Token not found',
                'tutor' => null
            ];
        }
        
        if (!$qrToken->is_active) {
            return [
                'valid' => false,
                'message' => 'Token is inactive',
                'tutor' => null
            ];
        }
        
        if ($qrToken->valid_until && $qrToken->valid_until < Carbon::now()) {
            return [
                'valid' => false,
                'message' => 'Token has expired',
                'tutor' => null
            ];
        }
        
        return [
            'valid' => true,
            'message' => 'Token is valid',
            'tutor' => $qrToken->tutor,
            'token' => $qrToken
        ];
    }
    
    public function invalidateToken($token)
    {
        $qrToken = QrToken::where('token', $token)->first();
        
        if (!$qrToken) {
            return false;
        }
        
        $qrToken->is_active = false;
        $qrToken->save();
        
        return true;
    }
    
    public function generateBatchTokens($studentIds, $validDays = 30)
    {
        $tokens = [];
        
        foreach ($studentIds as $id_anak) {
            try {
                $tokens[] = $this->generateToken($id_anak, $validDays);
            } catch (\Exception $e) {
                \Log::error("Failed to generate token for student ID {$id_anak}: " . $e->getMessage());
            }
        }
        
        return $tokens;
    }
    
    public function generateBatchTutorTokens($tutorIds, $validDays = 30)
    {
        $tokens = [];
        
        foreach ($tutorIds as $id_tutor) {
            try {
                $tokens[] = $this->generateTutorToken($id_tutor, $validDays);
            } catch (\Exception $e) {
                \Log::error("Failed to generate token for tutor ID {$id_tutor}: " . $e->getMessage());
            }
        }
        
        return $tokens;
    }
    
    protected function createSecureToken($type, $id)
    {
        $prefix = substr($type, 0, 1) . substr(str_pad($id, 5, '0', STR_PAD_LEFT), -5);
        $randomStr = Str::random(15);
        $timestamp = date('ymdHi');
        
        $token = hash('sha256', $prefix . $randomStr . $timestamp);
        
        $exists = QrToken::where('token', $token)->exists();
        if ($exists) {
            return $this->createSecureToken($type, $id);
        }
        
        return $token;
    }
}