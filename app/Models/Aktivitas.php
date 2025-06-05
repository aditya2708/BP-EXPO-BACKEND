<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Aktivitas extends Model
{
    use HasFactory;

    protected $table = 'aktivitas';
    protected $primaryKey = 'id_aktivitas';

    protected $fillable = [
        'id_shelter',
        'id_tutor',
        'jenis_kegiatan',
        'level',
        'nama_kelompok',
        'materi',
        'id_materi',
        'foto_1',
        'foto_2',
        'foto_3',
        'tanggal',
        'start_time',
        'end_time',
        'late_threshold',
        'late_minutes_threshold',
    ];

    protected $attributes = [
        'level' => '',
        'nama_kelompok' => '',
        'late_minutes_threshold' => 15,
    ];

    protected $casts = [
        'tanggal' => 'date',
        'late_minutes_threshold' => 'integer',
    ];

    protected $appends = ['foto_1_url', 'foto_2_url', 'foto_3_url'];

    public function shelter()
    {
        return $this->belongsTo(Shelter::class, 'id_shelter', 'id_shelter');
    }

    public function anak()
    {
        return $this->belongsTo(Anak::class, 'id_anak', 'id_anak');
    }

    public function materiData()
    {
        return $this->belongsTo(Materi::class, 'id_materi', 'id_materi');
    }

    public function absen()
    {
        return $this->hasMany(Absen::class, 'id_aktivitas');
    }

    public function tutor()
    {
        return $this->belongsTo(Tutor::class, 'id_tutor', 'id_tutor');
    }

    public function getFoto1UrlAttribute()
    {
        if ($this->foto_1) {
            return url("storage/Aktivitas/{$this->id_aktivitas}/{$this->foto_1}");
        }
        
        return url('images/default.png');
    }

    public function getFoto2UrlAttribute()
    {
        if ($this->foto_2) {
            return url("storage/Aktivitas/{$this->id_aktivitas}/{$this->foto_2}");
        }
        
        return url('images/default.png');
    }

    public function getFoto3UrlAttribute()
    {
        if ($this->foto_3) {
            return url("storage/Aktivitas/{$this->id_aktivitas}/{$this->foto_3}");
        }
        
        return url('images/default.png');
    }

    public function setLevelAttribute($value)
    {
        $this->attributes['level'] = $value ?? '';
    }

    public function setNamaKelompokAttribute($value)
    {
        $this->attributes['nama_kelompok'] = $value ?? '';
    }

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

    public function isLate($arrivalTime)
    {
        if (!$this->start_time) {
            return false;
        }

        $activityDate = $this->tanggal->format('Y-m-d');
        $arrivalDate = $arrivalTime->format('Y-m-d');
        
        if ($activityDate !== $arrivalDate) {
            return $activityDate < $arrivalDate;
        }

        $startTime = \Carbon\Carbon::parse($activityDate . ' ' . $this->start_time);
        
        if ($this->late_threshold) {
            $lateThreshold = \Carbon\Carbon::parse($activityDate . ' ' . $this->late_threshold);
        } else {
            $lateThreshold = $startTime->copy()->addMinutes($this->late_minutes_threshold ?? 15);
        }

        return $arrivalTime->gt($lateThreshold);
    }

    public function isAbsent($arrivalTime)
    {
        if (!$this->end_time) {
            return false;
        }

        $activityDate = $this->tanggal->format('Y-m-d');
        $arrivalDate = $arrivalTime->format('Y-m-d');
        
        if ($activityDate !== $arrivalDate) {
            return $activityDate < $arrivalDate;
        }

        $endTime = \Carbon\Carbon::parse($activityDate . ' ' . $this->end_time);
        return $arrivalTime->gt($endTime);
    }

    public function canRecordAttendance(\Carbon\Carbon $currentTime = null)
    {
        $currentTime = $currentTime ?? \Carbon\Carbon::now();
        $activityDate = $this->tanggal->startOfDay();
        $currentDate = $currentTime->startOfDay();
        
        if ($activityDate->gt($currentDate)) {
            return [
                'allowed' => false,
                'reason' => 'Activity has not started yet'
            ];
        }
        
        return [
            'allowed' => true,
            'reason' => null
        ];
    }

    public function isActivityExpired(\Carbon\Carbon $currentTime = null)
    {
        $currentTime = $currentTime ?? \Carbon\Carbon::now();
        $activityDate = $this->tanggal->startOfDay();
        $currentDate = $currentTime->startOfDay();
        
        return $activityDate->lt($currentDate);
    }

    public function isActivityToday(\Carbon\Carbon $currentTime = null)
    {
        $currentTime = $currentTime ?? \Carbon\Carbon::now();
        $activityDate = $this->tanggal->startOfDay();
        $currentDate = $currentTime->startOfDay();
        
        return $activityDate->eq($currentDate);
    }

    public function isActivityFuture(\Carbon\Carbon $currentTime = null)
    {
        $currentTime = $currentTime ?? \Carbon\Carbon::now();
        $activityDate = $this->tanggal->startOfDay();
        $currentDate = $currentTime->startOfDay();
        
        return $activityDate->gt($currentDate);
    }
}