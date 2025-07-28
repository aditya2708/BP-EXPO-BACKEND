<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MataPelajaran extends Model
{
    use HasFactory;

    protected $table = 'mata_pelajaran';
    protected $primaryKey = 'id_mata_pelajaran';

    protected $fillable = [
        'nama_mata_pelajaran',
        'kode_mata_pelajaran',
        'kategori',
        'deskripsi',
        'status',
        'id_kacab',
        'id_jenjang'
    ];

    protected $casts = [
        'id_mata_pelajaran' => 'integer',
        'id_kacab' => 'integer',
        'id_jenjang' => 'integer'
    ];

    protected $attributes = [
        'status' => 'aktif'
    ];

    // Relations - FIXED: KantorCabang → Kacab
    public function jenjang()
    {
        return $this->belongsTo(Jenjang::class, 'id_jenjang', 'id_jenjang');
    }

    public function kacab()
    {
        return $this->belongsTo(Kacab::class, 'id_kacab', 'id_kacab');
    }

    public function materi()
    {
        return $this->hasMany(Materi::class, 'id_mata_pelajaran', 'id_mata_pelajaran');
    }

    public function kurikulumMateri()
    {
        return $this->hasMany(KurikulumMateri::class, 'id_mata_pelajaran', 'id_mata_pelajaran');
    }

    public function kurikulum()
    {
        return $this->belongsToMany(
            Kurikulum::class,
            'kurikulum_materi',
            'id_mata_pelajaran',
            'id_kurikulum'
        )->withPivot(['id_materi', 'urutan'])->withTimestamps();
    }

    public function kelas()
    {
        return $this->belongsToMany(
            Kelas::class,
            'kelas_mata_pelajaran',
            'id_mata_pelajaran',
            'id_kelas'
        )->withTimestamps();
    }

    // Scopes
    public function scopeByJenjang($query, $jenjangId)
    {
        return $query->where('id_jenjang', $jenjangId);
    }

    public function scopeForAllJenjang($query)
    {
        return $query->whereNull('id_jenjang');
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'aktif');
    }

    public function scopeByKacab($query, $kacabId)
    {
        return $query->where('id_kacab', $kacabId);
    }

    public function scopeByKategori($query, $kategori)
    {
        return $query->where('kategori', $kategori);
    }

    public function scopeWithRelations($query)
    {
        return $query->with(['jenjang', 'kacab']);
    }

    public function scopeWithCounts($query)
    {
        return $query->withCount(['materi', 'kurikulumMateri', 'kelas']);
    }

    public function scopeSearch($query, $search)
    {
        return $query->where(function($q) use ($search) {
            $q->where('nama_mata_pelajaran', 'like', "%{$search}%")
              ->orWhere('kode_mata_pelajaran', 'like', "%{$search}%");
        });
    }

    public function scopeWajib($query)
    {
        return $query->where('kategori', 'wajib');
    }

    public function scopePilihan($query)
    {
        return $query->where('kategori', 'pilihan');
    }

    public function scopeMuatanLokal($query)
    {
        return $query->where('kategori', 'muatan_lokal');
    }

    public function scopeEkstrakurikuler($query)
    {
        return $query->where('kategori', 'ekstrakurikuler');
    }

    public function scopePengembanganDiri($query)
    {
        return $query->where('kategori', 'pengembangan_diri');
    }

    // Accessors
    public function getFullNameAttribute()
    {
        return $this->kode_mata_pelajaran ? 
            "{$this->kode_mata_pelajaran} - {$this->nama_mata_pelajaran}" : 
            $this->nama_mata_pelajaran;
    }

    public function getKategoriTextAttribute()
    {
        $kategoriMap = [
            'wajib' => 'Mata Pelajaran Wajib',
            'pilihan' => 'Mata Pelajaran Pilihan',
            'muatan_lokal' => 'Muatan Lokal',
            'ekstrakurikuler' => 'Ekstrakurikuler',
            'pengembangan_diri' => 'Pengembangan Diri'
        ];

        return $kategoriMap[$this->kategori] ?? ucfirst($this->kategori);
    }

    // Methods
    public function getTotalMateri()
    {
        return $this->materi()->count();
    }

    public function getTotalKelas()
    {
        return $this->kelas()->count();
    }

    public function getTotalKurikulum()
    {
        return $this->kurikulum()->distinct()->count();
    }

    public function getMataPelajaranStats()
    {
        return [
            'total_materi' => $this->getTotalMateri(),
            'total_kelas' => $this->getTotalKelas(),
            'total_kurikulum' => $this->getTotalKurikulum(),
            'kategori' => $this->kategori_text
        ];
    }

    public function isUsedInKurikulum()
    {
        return $this->kurikulum()->exists();
    }

    public function canBeDeleted()
    {
        return !$this->isUsedInKurikulum() && $this->materi()->count() === 0;
    }
}