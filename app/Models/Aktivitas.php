<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Aktivitas extends Model
{
    use HasFactory;

    // Nama tabel yang digunakan
    protected $table = 'aktivitas';

    // Primary key yang digunakan
    protected $primaryKey = 'id_aktivitas';

    // Kolom yang bisa diisi mass-assignment
    protected $fillable = [
    'id_shelter',
    'id_tutor',
    'jenis_kegiatan',
    'level',
    'nama_kelompok',
    'materi',
    'id_materi', // tambahkan ini
    'foto_1',
    'foto_2',
    'foto_3',
    'tanggal',
    'start_time',
    'end_time',
    'late_threshold',
    'late_minutes_threshold',
];

    // Default values for attributes
    protected $attributes = [
        'level' => '',
        'nama_kelompok' => '',
        'late_minutes_threshold' => 15,
    ];

    // Cast attributes to native types
    protected $casts = [
        'tanggal' => 'date',
        'late_minutes_threshold' => 'integer',
    ];

    // Tambahkan atribut yang akan ditambahkan ke JSON
    protected $appends = ['foto_1_url', 'foto_2_url', 'foto_3_url'];

    // Relasi ke model Shelter
    public function shelter()
    {
        return $this->belongsTo(Shelter::class, 'id_shelter', 'id_shelter');
    }

    // Relasi ke model Anak
    public function anak()
    {
        return $this->belongsTo(Anak::class, 'id_anak', 'id_anak');
    }
public function materiData()
{
    return $this->belongsTo(Materi::class, 'id_materi', 'id_materi');
}
    // Relasi ke model Absen
    public function absen()
    {
        return $this->hasMany(Absen::class, 'id_aktivitas');
    }

    // Accessor untuk foto_1 URL
    public function getFoto1UrlAttribute()
    {
        if ($this->foto_1) {
            return url("storage/Aktivitas/{$this->id_aktivitas}/{$this->foto_1}");
        }
        
        return url('images/default.png');
    }

    // Accessor untuk foto_2 URL
    public function getFoto2UrlAttribute()
    {
        if ($this->foto_2) {
            return url("storage/Aktivitas/{$this->id_aktivitas}/{$this->foto_2}");
        }
        
        return url('images/default.png');
    }

    // Accessor untuk foto_3 URL
    public function getFoto3UrlAttribute()
    {
        if ($this->foto_3) {
            return url("storage/Aktivitas/{$this->id_aktivitas}/{$this->foto_3}");
        }
        
        return url('images/default.png');
    }

    // Mutator for level - ensure it's never null
    public function setLevelAttribute($value)
    {
        $this->attributes['level'] = $value ?? '';
    }

    // Mutator for nama_kelompok - ensure it's never null
    public function setNamaKelompokAttribute($value)
    {
        $this->attributes['nama_kelompok'] = $value ?? '';
    }

    /**
     * Calculate the late threshold time based on start_time and late_minutes_threshold
     *
     * @return string|null
     */
    public function getLateThresholdTime()
    {
        if (!$this->start_time) {
            return null;
        }

        if ($this->late_threshold) {
            return $this->late_threshold;
        }

        $minutes = $this->late_minutes_threshold ?? 15;
        return \Carbon\Carbon::parse($this->start_time)->addMinutes($minutes)->format('H:i:s');
    }

/**
 * Check if a given time is considered late for this activity
 *
 * @param \Carbon\Carbon $arrivalTime
 * @return bool
 */
public function isLate($arrivalTime)
{
    if (!$this->start_time) {
        return false;
    }

    $activityDate = $this->tanggal->format('Y-m-d');
    $startTime = \Carbon\Carbon::parse($activityDate . ' ' . $this->start_time);
    
    // Create arrival time with activity date but arrival's time component
    $arrivalTimeForComparison = \Carbon\Carbon::parse($activityDate . ' ' . $arrivalTime->format('H:i:s'));
    
    // If late_threshold is set, use it
    if ($this->late_threshold) {
        $lateThreshold = \Carbon\Carbon::parse($activityDate . ' ' . $this->late_threshold);
    } else {
        // Otherwise use start_time plus the minutes threshold
        $lateThreshold = $startTime->copy()->addMinutes($this->late_minutes_threshold ?? 15);
    }

    return $arrivalTimeForComparison->gt($lateThreshold);
}

  /**
 * Check if a given time is considered absent for this activity
 *
 * @param \Carbon\Carbon $arrivalTime
 * @return bool
 */
public function isAbsent($arrivalTime)
{
    if (!$this->end_time) {
        return false;
    }

    $activityDate = $this->tanggal->format('Y-m-d');
    $endTime = \Carbon\Carbon::parse($activityDate . ' ' . $this->end_time);
    
    // Create arrival time with activity date but arrival's time component
    $arrivalTimeForComparison = \Carbon\Carbon::parse($activityDate . ' ' . $arrivalTime->format('H:i:s'));

    return $arrivalTimeForComparison->gt($endTime);
}
/**
 * Relasi ke model Tutor
 */
public function tutor()
{
    return $this->belongsTo(Tutor::class, 'id_tutor', 'id_tutor');
}
}