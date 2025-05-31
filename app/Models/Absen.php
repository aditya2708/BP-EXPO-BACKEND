<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Absen extends Model
{
    use HasFactory;

    protected $table = 'absen'; // Nama tabel
    protected $primaryKey = 'id_absen'; // Primary key
    protected $fillable = [
        'absen',          // Kolom untuk status absen (Ya/Tidak/Terlambat)
        'id_absen_user',  // Relasi dengan AbsenUser
        'id_aktivitas',   // Relasi dengan Aktivitas
        'id_donatur',     // Relasi dengan Donatur
        'is_read',        // Kolom status baca
        'is_verified',    // New field - verification status
        'verification_status', // New field - details on verification status
        'time_arrived',   // Time when attendance was recorded
        'created_at',     // Waktu pembuatan
        'updated_at',     // Waktu pembaruan
    ]; // Kolom yang bisa diisi

    protected $casts = [
        'is_verified' => 'boolean',
        'time_arrived' => 'datetime'
    ];

    const TEXT_YA = "Ya";
    const TEXT_TIDAK = "Tidak";
    const TEXT_TERLAMBAT = "Terlambat";
    const TYPE_YA = 1;
    const TYPE_TIDAK = 2;
    const TYPE_TERLAMBAT = 3;

    // Verification status constants
    const VERIFICATION_PENDING = 'pending';
    const VERIFICATION_VERIFIED = 'verified';
    const VERIFICATION_REJECTED = 'rejected';
    const VERIFICATION_MANUAL = 'manual';

    // Relasi dengan AbsenUser
    public function absenUser() {
        return $this->belongsTo(AbsenUser::class, 'id_absen_user');
    }

    // Relasi dengan Aktivitas
    public function aktivitas()
    {
        return $this->belongsTo(Aktivitas::class, 'id_aktivitas');
    }

    // Relasi dengan Donatur
    public function donatur()
    {
        return $this->belongsTo(Donatur::class, 'id_donatur');
    }

    // New relation with AttendanceVerification
    public function verifications()
    {
        return $this->hasMany(AttendanceVerification::class, 'id_absen');
    }

    /**
     * Scope a query to only include verified attendance records
     */
    public function scopeVerified($query)
    {
        return $query->where('is_verified', true);
    }

    /**
     * Scope a query to only include pending verification records
     */
    public function scopePending($query)
    {
        return $query->where('verification_status', self::VERIFICATION_PENDING);
    }

    /**
     * Scope a query to only include late attendance records
     */
    public function scopeLate($query)
    {
        return $query->where('absen', self::TEXT_TERLAMBAT);
    }

    /**
     * Get the latest verification record
     */
    public function latestVerification()
    {
        return $this->verifications()->latest()->first();
    }

    /**
     * Check if attendance is late
     */
    public function isLate()
    {
        return $this->absen === self::TEXT_TERLAMBAT;
    }

    /**
     * Check if attendance is present (including late)
     */
    public function isPresent()
    {
        return $this->absen === self::TEXT_YA || $this->absen === self::TEXT_TERLAMBAT;
    }
}