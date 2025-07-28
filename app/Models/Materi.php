<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Materi extends Model
{
    use HasFactory;

    protected $table = 'materi';
    protected $primaryKey = 'id_materi';

    protected $fillable = [
        'id_mata_pelajaran',
        'id_kelas',
        'nama_materi'
    ];

    protected $casts = [
        'id_materi' => 'integer',
        'id_mata_pelajaran' => 'integer',
        'id_kelas' => 'integer'
    ];

    // Relations
    public function mataPelajaran()
    {
        return $this->belongsTo(MataPelajaran::class, 'id_mata_pelajaran', 'id_mata_pelajaran');
    }

    public function kelas()
    {
        return $this->belongsTo(Kelas::class, 'id_kelas', 'id_kelas');
    }

    public function kurikulumMateri()
    {
        return $this->hasMany(KurikulumMateri::class, 'id_materi', 'id_materi');
    }

    public function kurikulum()
    {
        return $this->belongsToMany(
            Kurikulum::class,
            'kurikulum_materi',
            'id_materi',
            'id_kurikulum'
        )->withPivot(['id_mata_pelajaran', 'urutan'])->withTimestamps();
    }

    // Scopes
    public function scopeByKelas($query, $kelasId)
    {
        return $query->where('id_kelas', $kelasId);
    }

    public function scopeByJenjang($query, $jenjangId)
    {
        return $query->whereHas('kelas', function($q) use ($jenjangId) {
            $q->where('id_jenjang', $jenjangId);
        });
    }

    public function scopeByMataPelajaran($query, $mataPelajaranId)
    {
        return $query->where('id_mata_pelajaran', $mataPelajaranId);
    }

    public function scopeByKacab($query, $kacabId)
    {
        return $query->whereHas('mataPelajaran', function($q) use ($kacabId) {
            $q->where('id_kacab', $kacabId);
        });
    }

    public function scopeWithRelations($query)
    {
        return $query->with(['mataPelajaran.jenjang', 'kelas.jenjang']);
    }

    public function scopeWithCounts($query)
    {
        return $query->withCount(['kurikulumMateri']);
    }

    public function scopeSearch($query, $search)
    {
        return $query->where('nama_materi', 'like', "%{$search}%");
    }

    public function scopeUsedInKurikulum($query)
    {
        return $query->has('kurikulumMateri');
    }

    public function scopeNotUsedInKurikulum($query)
    {
        return $query->doesntHave('kurikulumMateri');
    }

    public function scopeAvailableForKurikulum($query, $kurikulumId)
    {
        return $query->whereNotIn('id_materi', function($q) use ($kurikulumId) {
            $q->select('id_materi')
              ->from('kurikulum_materi')
              ->where('id_kurikulum', $kurikulumId);
        });
    }

    // Methods
    public function getTotalKurikulum()
    {
        return $this->kurikulum()->distinct()->count();
    }

    public function canBeDeleted()
    {
        return $this->kurikulumMateri()->count() === 0;
    }

    public function isValidCombination()
    {
        if (!$this->mataPelajaran || !$this->kelas) {
            return false;
        }
        
        return $this->mataPelajaran->id_jenjang === $this->kelas->id_jenjang;
    }

    public function isUsedInKurikulum()
    {
        return $this->kurikulumMateri()->exists();
    }

    public function getKurikulumCount()
    {
        return $this->kurikulum()->distinct()->count();
    }

    public function getUsageStatistics()
    {
        return [
            'total_kurikulum' => $this->getTotalKurikulum(),
            'is_used' => $this->isUsedInKurikulum(),
            'can_be_deleted' => $this->canBeDeleted()
        ];
    }

    public function getStatistics()
    {
        return [
            'total_kurikulum' => $this->getTotalKurikulum(),
            'mata_pelajaran' => $this->mataPelajaran->nama_mata_pelajaran ?? 'N/A',
            'kelas' => $this->kelas->display_name ?? 'N/A',
            'jenjang' => $this->jenjang->nama_jenjang ?? 'N/A',
            'kategori_mata_pelajaran' => $this->mataPelajaran->kategori ?? 'N/A',
            'is_valid_combination' => $this->isValidCombination(),
            'can_be_deleted' => $this->canBeDeleted(),
            'is_used_in_kurikulum' => $this->isUsedInKurikulum()
        ];
    }

    // Validation Methods
    public static function validateUnique($mataPelajaranId, $kelasId, $namaMateri, $excludeId = null)
    {
        $query = static::where('id_mata_pelajaran', $mataPelajaranId)
            ->where('id_kelas', $kelasId)
            ->where('nama_materi', $namaMateri);
        
        if ($excludeId) {
            $query->where('id_materi', '!=', $excludeId);
        }
        
        return !$query->exists();
    }

    public static function validateJenjangConsistency($mataPelajaranId, $kelasId)
    {
        $mataPelajaran = MataPelajaran::find($mataPelajaranId);
        $kelas = Kelas::find($kelasId);
        
        if (!$mataPelajaran || !$kelas) {
            return false;
        }
        
        return $mataPelajaran->id_jenjang === $kelas->id_jenjang;
    }

    public function validateMataPelajaranKelas()
    {
        return static::validateJenjangConsistency($this->id_mata_pelajaran, $this->id_kelas);
    }

    // Helper Methods
    public static function getForDropdown($kacabId = null, $mataPelajaranId = null, $kelasId = null, $jenjangId = null)
    {
        $query = static::with(['mataPelajaran', 'kelas']);
        
        if ($kacabId) {
            $query->byKacab($kacabId);
        }
        
        if ($mataPelajaranId) {
            $query->byMataPelajaran($mataPelajaranId);
        }
        
        if ($kelasId) {
            $query->byKelas($kelasId);
        }
        
        if ($jenjangId) {
            $query->byJenjang($jenjangId);
        }
        
        return $query->orderBy('nama_materi')
            ->get(['id_materi', 'nama_materi', 'id_mata_pelajaran', 'id_kelas']);
    }

    public static function getByMataPelajaranAndKelas($mataPelajaranId, $kelasId = null)
    {
        $query = static::byMataPelajaran($mataPelajaranId)
            ->with(['kelas', 'mataPelajaran']);
        
        if ($kelasId) {
            $query->byKelas($kelasId);
        }
        
        return $query->orderBy('nama_materi')->get();
    }

    public static function getByKelasGrouped($kelasId)
    {
        return static::byKelas($kelasId)
            ->with(['mataPelajaran'])
            ->withCounts()
            ->orderBy('id_mata_pelajaran')
            ->orderBy('nama_materi')
            ->get()
            ->groupBy('mataPelajaran.nama_mata_pelajaran');
    }

    public static function getUsageReport($kacabId)
    {
        $totalMateri = static::byKacab($kacabId)->count();
        $usedMateri = static::byKacab($kacabId)->usedInKurikulum()->count();
        $unusedMateri = $totalMateri - $usedMateri;
        
        return [
            'total_materi' => $totalMateri,
            'used_materi' => $usedMateri,
            'unused_materi' => $unusedMateri,
            'usage_percentage' => $totalMateri > 0 ? round(($usedMateri / $totalMateri) * 100, 2) : 0
        ];
    }

    // Accessors
    public function getFullNameAttribute()
    {
        $kelas = $this->kelas ? $this->kelas->display_name : 'N/A';
        $mataPelajaran = $this->mataPelajaran ? $this->mataPelajaran->nama_mata_pelajaran : 'N/A';
        return "{$this->nama_materi} ({$mataPelajaran} - {$kelas})";
    }

    public function getJenjangAttribute()
    {
        return $this->kelas ? $this->kelas->jenjang : null;
    }

    public function getDisplayNameAttribute()
    {
        return $this->nama_materi;
    }

    public function getMataPelajaranNameAttribute()
    {
        return $this->mataPelajaran ? $this->mataPelajaran->nama_mata_pelajaran : 'N/A';
    }

    public function getKelasNameAttribute()
    {
        return $this->kelas ? $this->kelas->display_name : 'N/A';
    }

    public function getJenjangNameAttribute()
    {
        return $this->jenjang ? $this->jenjang->nama_jenjang : 'N/A';
    }

    public function getKategoriMataPelajaranAttribute()
    {
        return $this->mataPelajaran ? $this->mataPelajaran->kategori_text : 'N/A';
    }

    public function getUsageStatusAttribute()
    {
        return $this->isUsedInKurikulum() ? 'Digunakan' : 'Tidak Digunakan';
    }

    public function getUsageColorAttribute()
    {
        return $this->isUsedInKurikulum() ? '#27ae60' : '#95a5a6';
    }
}