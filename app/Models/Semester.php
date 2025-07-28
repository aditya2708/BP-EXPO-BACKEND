<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Semester extends Model
{
    use HasFactory;

    protected $table = 'semester';
    protected $primaryKey = 'id_semester';

    protected $fillable = [
        'nama_semester',
        'tahun_ajaran',
        'periode',
        'tanggal_mulai',
        'tanggal_selesai',
        'kurikulum_id',
        'is_active',
        'id_shelter'
    ];

    protected $casts = [
        'id_semester' => 'integer',
        'tanggal_mulai' => 'date',
        'tanggal_selesai' => 'date',
        'kurikulum_id' => 'integer',
        'is_active' => 'boolean',
        'id_shelter' => 'integer'
    ];

    protected $dates = [
        'tanggal_mulai',
        'tanggal_selesai',
        'created_at',
        'updated_at'
    ];

    // Relations
    public function shelter()
    {
        return $this->belongsTo(Shelter::class, 'id_shelter', 'id_shelter');
    }

    public function kurikulum()
    {
        return $this->belongsTo(Kurikulum::class, 'kurikulum_id', 'id_kurikulum');
    }

    public function kelas()
    {
        return $this->hasMany(Kelas::class, 'id_semester', 'id_semester');
    }

    public function jadwal()
    {
        return $this->hasMany(Jadwal::class, 'id_semester', 'id_semester');
    }

    public function absensi()
    {
        return $this->hasMany(Absensi::class, 'id_semester', 'id_semester');
    }

    public function penilaian()
    {
        return $this->hasMany(Penilaian::class, 'id_semester', 'id_semester');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }

    public function scopeByShelter($query, $shelterId)
    {
        return $query->where('id_shelter', $shelterId);
    }

    public function scopeByTahunAjaran($query, $tahun)
    {
        return $query->where('tahun_ajaran', $tahun);
    }

    public function scopeByPeriode($query, $periode)
    {
        return $query->where('periode', $periode);
    }

    public function scopeCurrentPeriod($query)
    {
        $now = now();
        return $query->where('tanggal_mulai', '<=', $now)
                    ->where('tanggal_selesai', '>=', $now);
    }

    public function scopeWithKurikulum($query)
    {
        return $query->whereNotNull('kurikulum_id');
    }

    public function scopeWithoutKurikulum($query)
    {
        return $query->whereNull('kurikulum_id');
    }

    // Accessors
    public function getStatusAttribute()
    {
        $now = now();
        
        if ($now < $this->tanggal_mulai) {
            return 'upcoming';
        } elseif ($now > $this->tanggal_selesai) {
            return 'finished';
        } else {
            return 'ongoing';
        }
    }

    public function getStatusLabelAttribute()
    {
        return match($this->status) {
            'upcoming' => 'Akan Datang',
            'ongoing' => 'Berlangsung',
            'finished' => 'Selesai',
            default => 'Tidak Diketahui'
        };
    }

    public function getDurasiHariAttribute()
    {
        return $this->tanggal_mulai->diffInDays($this->tanggal_selesai) + 1;
    }

    public function getProgressPercentAttribute()
    {
        $now = now();
        
        if ($now < $this->tanggal_mulai) {
            return 0;
        } elseif ($now > $this->tanggal_selesai) {
            return 100;
        }
        
        $totalDays = $this->tanggal_mulai->diffInDays($this->tanggal_selesai);
        $passedDays = $this->tanggal_mulai->diffInDays($now);
        
        return $totalDays > 0 ? round(($passedDays / $totalDays) * 100, 2) : 0;
    }

    public function getHasKurikulumAttribute()
    {
        return !is_null($this->kurikulum_id);
    }

    // Validation rules
    public static function validationRules($isUpdate = false)
    {
        $rules = [
            'nama_semester' => 'required|string|max:255',
            'tahun_ajaran' => 'required|string|max:20',
            'periode' => 'required|in:ganjil,genap',
            'tanggal_mulai' => 'required|date',
            'tanggal_selesai' => 'required|date|after:tanggal_mulai',
            'kurikulum_id' => 'nullable|exists:kurikulum,id_kurikulum',
            'is_active' => 'boolean',
            'id_shelter' => 'required|exists:shelter,id_shelter'
        ];

        if ($isUpdate) {
            foreach ($rules as $key => $rule) {
                if ($key !== 'id_shelter') {
                    $rules[$key] = str_replace('required|', 'sometimes|required|', $rule);
                }
            }
        }

        return $rules;
    }

    // Custom validation messages
    public static function validationMessages()
    {
        return [
            'nama_semester.required' => 'Nama semester harus diisi',
            'nama_semester.max' => 'Nama semester maksimal 255 karakter',
            'tahun_ajaran.required' => 'Tahun ajaran harus diisi',
            'tahun_ajaran.max' => 'Tahun ajaran maksimal 20 karakter',
            'periode.required' => 'Periode harus dipilih',
            'periode.in' => 'Periode harus ganjil atau genap',
            'tanggal_mulai.required' => 'Tanggal mulai harus diisi',
            'tanggal_mulai.date' => 'Format tanggal mulai tidak valid',
            'tanggal_selesai.required' => 'Tanggal selesai harus diisi',
            'tanggal_selesai.date' => 'Format tanggal selesai tidak valid',
            'tanggal_selesai.after' => 'Tanggal selesai harus setelah tanggal mulai',
            'kurikulum_id.exists' => 'Kurikulum tidak ditemukan',
            'is_active.boolean' => 'Status aktif harus true atau false',
            'id_shelter.required' => 'Shelter harus dipilih',
            'id_shelter.exists' => 'Shelter tidak ditemukan'
        ];
    }

    // Helper methods
    public function isActive()
    {
        return $this->is_active;
    }

    public function isOngoing()
    {
        return $this->status === 'ongoing';
    }

    public function isFinished()
    {
        return $this->status === 'finished';
    }

    public function isUpcoming()
    {
        return $this->status === 'upcoming';
    }

    public function canBeActivated()
    {
        return $this->status !== 'finished';
    }

    public function canBeDeleted()
    {
        return !$this->kelas()->exists() && 
               !$this->jadwal()->exists() && 
               !$this->absensi()->exists() && 
               !$this->penilaian()->exists();
    }

    public function getTotalSiswa()
    {
        return $this->kelas()->withCount('siswa')->sum('siswa_count');
    }

    public function getTotalKelas()
    {
        return $this->kelas()->count();
    }

    public function getTotalJadwal()
    {
        return $this->jadwal()->count();
    }

    // Event listeners
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($semester) {
            // Auto-generate nama_semester if not provided
            if (empty($semester->nama_semester)) {
                $semester->nama_semester = "Semester {$semester->periode} {$semester->tahun_ajaran}";
            }
        });

        static::updating(function ($semester) {
            // If setting as active, deactivate others in same shelter
            if ($semester->is_active && $semester->isDirty('is_active')) {
                static::where('id_shelter', $semester->id_shelter)
                     ->where('id_semester', '!=', $semester->id_semester)
                     ->update(['is_active' => false]);
            }
        });
    }
}