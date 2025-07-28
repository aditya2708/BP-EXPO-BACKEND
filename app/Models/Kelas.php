<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Kelas extends Model
{
    use HasFactory;

    protected $table = 'kelas';
    protected $primaryKey = 'id_kelas';

    protected $fillable = [
        'id_jenjang',
        'nama_kelas',
        'tingkat',
        'jenis_kelas',
        'is_custom',
        'urutan',
        'deskripsi',
        'is_active'
    ];

    protected $casts = [
        'id_kelas' => 'integer',
        'id_jenjang' => 'integer',
        'tingkat' => 'integer',
        'urutan' => 'integer',
        'is_custom' => 'boolean',
        'is_active' => 'boolean'
    ];

    // Relations
    public function jenjang()
    {
        return $this->belongsTo(Jenjang::class, 'id_jenjang', 'id_jenjang');
    }

    public function materi()
    {
        return $this->hasMany(Materi::class, 'id_kelas', 'id_kelas');
    }

    public function materiWithMataPelajaran()
    {
        return $this->hasMany(Materi::class, 'id_kelas', 'id_kelas')
            ->with('mataPelajaran');
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

    public function scopeStandard($query)
    {
        return $query->where('jenis_kelas', 'standard');
    }

    public function scopeCustom($query)
    {
        return $query->where('jenis_kelas', 'custom');
    }

    public function scopeByJenjang($query, $jenjangId)
    {
        return $query->where('id_jenjang', $jenjangId);
    }

    public function scopeByTingkat($query, $tingkat)
    {
        return $query->where('tingkat', $tingkat);
    }

    public function scopeOrderByUrutan($query, $direction = 'asc')
    {
        return $query->orderBy('urutan', $direction);
    }

    public function scopeWithCounts($query)
    {
        return $query->withCount(['materi']);
    }

    public function scopeSearch($query, $search)
    {
        return $query->where(function($q) use ($search) {
            $q->where('nama_kelas', 'like', "%{$search}%")
              ->orWhere('deskripsi', 'like', "%{$search}%")
              ->orWhere('tingkat', 'like', "%{$search}%");
        });
    }

    // Methods
    public function getTotalMateri()
    {
        return $this->materi()->count();
    }

    public function isStandard()
    {
        return $this->jenis_kelas === 'standard';
    }

    public function isCustom()
    {
        return $this->jenis_kelas === 'custom';
    }

    public function canBeDeleted()
    {
        return $this->materi()->count() === 0;
    }

    public function canBeDeactivated()
    {
        return $this->materi()->count() === 0;
    }

    public function hasMateri()
    {
        return $this->materi()->exists();
    }

    public function getRomanNumeral()
    {
        if (!$this->isStandard() || !$this->tingkat) {
            return null;
        }

        $numerals = [
            1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V', 6 => 'VI',
            7 => 'VII', 8 => 'VIII', 9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII'
        ];

        return $numerals[$this->tingkat] ?? $this->tingkat;
    }

    public function getStatistics()
    {
        return [
            'total_materi' => $this->getTotalMateri(),
            'jenis_kelas' => $this->jenis_kelas,
            'tingkat' => $this->tingkat,
            'urutan' => $this->urutan,
            'is_active' => $this->is_active,
            'jenjang' => $this->jenjang->nama_jenjang ?? 'N/A',
            'can_be_deleted' => $this->canBeDeleted(),
            'can_be_deactivated' => $this->canBeDeactivated()
        ];
    }

    // Validation Methods
    public static function validateStandardUnique($jenjangId, $tingkat, $excludeId = null)
    {
        $query = static::byJenjang($jenjangId)
            ->standard()
            ->where('tingkat', $tingkat);
        
        if ($excludeId) {
            $query->where('id_kelas', '!=', $excludeId);
        }
        
        return !$query->exists();
    }

    public static function validateCustomUnique($jenjangId, $namaKelas, $excludeId = null)
    {
        $query = static::byJenjang($jenjangId)
            ->custom()
            ->where('nama_kelas', $namaKelas);
        
        if ($excludeId) {
            $query->where('id_kelas', '!=', $excludeId);
        }
        
        return !$query->exists();
    }

    public static function validateUrutanUnique($jenjangId, $urutan, $excludeId = null)
    {
        $query = static::byJenjang($jenjangId)
            ->where('urutan', $urutan);
        
        if ($excludeId) {
            $query->where('id_kelas', '!=', $excludeId);
        }
        
        return !$query->exists();
    }

    public function validateTingkatRequired()
    {
        if ($this->isStandard()) {
            return !is_null($this->tingkat) && $this->tingkat > 0;
        }
        return true;
    }

    // Helper Methods
    public static function getForDropdown($jenjangId = null, $jenisKelas = null)
    {
        $query = static::active()->with('jenjang');
        
        if ($jenjangId) {
            $query->byJenjang($jenjangId);
        }
        
        if ($jenisKelas) {
            $query->where('jenis_kelas', $jenisKelas);
        }
        
        return $query->orderBy('jenis_kelas')
            ->orderByUrutan()
            ->get(['id_kelas', 'nama_kelas', 'jenis_kelas', 'tingkat', 'urutan', 'id_jenjang']);
    }

    public static function getByJenjangGrouped($jenjangId)
    {
        $kelas = static::byJenjang($jenjangId)
            ->active()
            ->withCounts()
            ->orderByUrutan()
            ->get();
            
        return [
            'standard' => $kelas->where('jenis_kelas', 'standard')->values(),
            'custom' => $kelas->where('jenis_kelas', 'custom')->values()
        ];
    }

    public static function getNextUrutan($jenjangId)
    {
        return static::byJenjang($jenjangId)->max('urutan') + 1;
    }

    public static function getTingkatOptions()
    {
        return collect(range(1, 12))->map(function($num) {
            $romans = [
                1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V', 6 => 'VI',
                7 => 'VII', 8 => 'VIII', 9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII'
            ];
            return [
                'value' => $num,
                'label' => "{$num} ({$romans[$num]})"
            ];
        });
    }

    // Accessors
    public function getFullNameAttribute()
    {
        if ($this->isStandard()) {
            return ($this->jenjang ? $this->jenjang->kode_jenjang : '') . ' - ' . $this->nama_kelas;
        }
        return $this->nama_kelas . ' (' . ($this->jenjang ? $this->jenjang->kode_jenjang : 'N/A') . ')';
    }

    public function getDisplayNameAttribute()
    {
        if ($this->isStandard() && $this->tingkat) {
            return 'Kelas ' . $this->getRomanNumeral();
        }
        return $this->nama_kelas;
    }

    public function getTypeBadgeAttribute()
    {
        return [
            'standard' => ['text' => 'Standard', 'color' => '#3498db'],
            'custom' => ['text' => 'Custom', 'color' => '#e74c3c']
        ][$this->jenis_kelas] ?? ['text' => 'Unknown', 'color' => '#95a5a6'];
    }

    public function getStatusTextAttribute()
    {
        return $this->is_active ? 'Aktif' : 'Tidak Aktif';
    }

    public function getStatusColorAttribute()
    {
        return $this->is_active ? '#27ae60' : '#95a5a6';
    }

    public function getJenjangNameAttribute()
    {
        return $this->jenjang ? $this->jenjang->nama_jenjang : 'N/A';
    }

    public function getTingkatTextAttribute()
    {
        if ($this->isStandard() && $this->tingkat) {
            return $this->tingkat . ' (' . $this->getRomanNumeral() . ')';
        }
        return $this->tingkat ?? 'N/A';
    }

    // Boot method
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($kelas) {
            // Auto-generate nama_kelas for standard classes
            if ($kelas->isStandard() && $kelas->tingkat) {
                $kelas->nama_kelas = 'Kelas ' . $kelas->getRomanNumeral();
            }
        });

        static::updating(function ($kelas) {
            // Auto-update nama_kelas for standard classes
            if ($kelas->isStandard() && $kelas->tingkat) {
                $kelas->nama_kelas = 'Kelas ' . $kelas->getRomanNumeral();
            }
        });
    }
}