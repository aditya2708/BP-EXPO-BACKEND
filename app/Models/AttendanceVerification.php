<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttendanceVerification extends Model
{
    use HasFactory;

    protected $table = 'attendance_verifications';
    protected $primaryKey = 'id_verification';
    
    protected $fillable = [
        'id_absen',
        'verification_method',
        'is_verified',
        'verification_notes',
        'verified_by',
        'verified_at',
        'metadata'
    ];

    protected $casts = [
        'is_verified' => 'boolean',
        'verified_at' => 'datetime',
        'metadata' => 'array'
    ];

    // Verification methods
    const METHOD_QR = 'qr_code';
    const METHOD_MANUAL = 'manual';
    const METHOD_FACE = 'face_recognition';
    const METHOD_DUAL = 'dual';

    /**
     * Get the attendance record this verification belongs to
     */
    public function absen()
    {
        return $this->belongsTo(Absen::class, 'id_absen', 'id_absen');
    }

    /**
     * Scope a query to only include verified records
     */
    public function scopeVerified($query)
    {
        return $query->where('is_verified', true);
    }

    /**
     * Scope a query to only include records with a specific verification method
     */
    public function scopeMethod($query, $method)
    {
        return $query->where('verification_method', $method);
    }
}