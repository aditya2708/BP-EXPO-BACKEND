<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Jenjang extends Model
{
    use HasFactory;

    protected $table = 'jenjang';
    protected $primaryKey = 'id_jenjang';

    protected $fillable = [
        'nama_jenjang',
        'kode_jenjang',
        'urutan',
        'deskripsi',
        'is_active'
    ];

    protected $casts = [
        'id_jenjang' => 'integer',
        'urutan' => 'integer',
        'is_active' => 'boolean'
    ];

    // Relations
    public function kelas()
    {
        return $this->hasMany(Kelas::class, 'id_jenjang', 'id_jenjang');
    }

    public function kelasStandard()
    {
        return $this->hasMany(Kelas::class, 'id_jenjang', 'id_jenjang')
            ->where('jenis_kelas', 'standard');
    }

    public function kelasCustom()
    {
        return $this->hasMany(Kelas::class, 'id_jenjang', 'id_jenjang')
            ->where('jenis_kelas', 'custom');
    }

    public function mataPelajaran()
    {
        return $this->hasMany(MataPelajaran::class, 'id_jenjang', 'id_jenjang');
    }

    public function materi()
    {
        return $this->hasManyThrough(
            Materi::class,
            Kelas::class,
            'id_jenjang',
            'id_kelas',
            'id_jenjang',
            'id_kelas'
        );
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

    public function scopeOrderByUrutan($query, $direction = 'asc')
    {
        return $query->orderBy('urutan', $direction);
    }

    public function scopeWithCounts($query)
    {
        return $query->withCount([
            'kelas', 
            'kelasStandard', 
            'kelasCustom', 
            'mataPelajaran'
        ]);
    }

    public function scopeSearch($query, $search)
    {
        return $query->where(function($q) use ($search) {
            $q->where('nama_jenjang', 'like', "%{$search}%")
              ->orWhere('kode_jenjang', 'like', "%{$search}%")
              ->orWhere('deskripsi', 'like', "%{$search}%");
        });
    }

    // Methods
    public function getTotalKelas()
    {
        return $this->kelas()->count();
    }

    public function getTotalMataPelajaran()
    {
        return $this->mataPelajaran()->count();
    }

    public function getKelasStandardCount()
    {
        return $this->kelasStandard()->count();
    }

    public function getKelasCustomCount()
    {
        return $this->kelasCustom()->count();
    }

    public function getTotalMateri()
    {
        return $this->materi()->count();
    }

    public function canBeDeleted()
    {
        return $this->kelas()->count() === 0 && 
               $this->mataPelajaran()->count() === 0;
    }

    public function canBeDeactivated()
    {
        return $this->kelas()->where('is_active', true)->count() === 0;
    }

    public function hasActiveKelas()
    {
        return $this->kelas()->where('is_active', true)->exists();
    }

    public function hasMataPelajaran()
    {
        return $this->mataPelajaran()->exists();
    }

    public function getStatistics()
    {
        return [
            'total_kelas' => $this->getTotalKelas(),
            'kelas_standard' => $this->getKelasStandardCount(),
            'kelas_custom' => $this->getKelasCustomCount(),
            'total_mata_pelajaran' => $this->getTotalMataPelajaran(),
            'total_materi' => $this->getTotalMateri(),
            'is_active' => $this->is_active,
            'can_be_deleted' => $this->canBeDeleted(),
            'can_be_deactivated' => $this->canBeDeactivated()
        ];
    }

    // Validation Methods (global scope)
    public static function validateUrutan($urutan, $excludeId = null)
    {
        $query = static::where('urutan', $urutan);
        
        if ($excludeId) {
            $query->where('id_jenjang', '!=', $excludeId);
        }
        
        return !$query->exists();
    }

    public static function validateKodeJenjang($kode, $excludeId = null)
    {
        $query = static::where('kode_jenjang', $kode);
        
        if ($excludeId) {
            $query->where('id_jenjang', '!=', $excludeId);
        }
        
        return !$query->exists();
    }

    public static function validateNamaJenjang($nama, $excludeId = null)
    {
        $query = static::where('nama_jenjang', $nama);
        
        if ($excludeId) {
            $query->where('id_jenjang', '!=', $excludeId);
        }
        
        return !$query->exists();
    }

    // Helper Methods
    public static function getNextUrutan()
    {
        return static::max('urutan') + 1;
    }

    public static function getForDropdown()
    {
        return static::active()
            ->orderByUrutan()
            ->get(['id_jenjang', 'nama_jenjang', 'kode_jenjang']);
    }

    public function reorderAfterDelete()
    {
        $jenjangAfter = static::where('urutan', '>', $this->urutan)
            ->orderBy('urutan')
            ->get();

        foreach ($jenjangAfter as $jenjang) {
            $jenjang->update(['urutan' => $jenjang->urutan - 1]);
        }
    }

    // Accessors
    public function getFullNameAttribute()
    {
        return $this->kode_jenjang . ' - ' . $this->nama_jenjang;
    }

    public function getDisplayNameAttribute()
    {
        return $this->nama_jenjang;
    }

    public function getStatusTextAttribute()
    {
        return $this->is_active ? 'Aktif' : 'Tidak Aktif';
    }

    public function getStatusColorAttribute()
    {
        return $this->is_active ? '#27ae60' : '#95a5a6';
    }

    // Boot method
    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($jenjang) {
            $jenjang->reorderAfterDelete();
        });
    }
}