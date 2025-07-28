<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KurikulumMateri extends Model
{
    use HasFactory;

    protected $table = 'kurikulum_materi';

    protected $fillable = [
        'id_kurikulum',
        'id_mata_pelajaran',
        'id_materi',
        'urutan'
    ];

    protected $casts = [
        'id_kurikulum' => 'integer',
        'id_mata_pelajaran' => 'integer',
        'id_materi' => 'integer',
        'urutan' => 'integer'
    ];

    // Relations
    public function kurikulum()
    {
        return $this->belongsTo(Kurikulum::class, 'id_kurikulum', 'id_kurikulum');
    }

    public function mataPelajaran()
    {
        return $this->belongsTo(MataPelajaran::class, 'id_mata_pelajaran', 'id_mata_pelajaran');
    }

    public function materi()
    {
        return $this->belongsTo(Materi::class, 'id_materi', 'id_materi');
    }

    // Scopes
    public function scopeByKurikulum($query, $kurikulumId)
    {
        return $query->where('id_kurikulum', $kurikulumId);
    }

    public function scopeByMataPelajaran($query, $mataPelajaranId)
    {
        return $query->where('id_mata_pelajaran', $mataPelajaranId);
    }

    public function scopeOrderByUrutan($query, $direction = 'asc')
    {
        return $query->orderBy('urutan', $direction);
    }

    public function scopeWithRelations($query)
    {
        return $query->with(['kurikulum', 'mataPelajaran.jenjang', 'materi.kelas']);
    }

    public function scopeByJenjang($query, $jenjangId)
    {
        return $query->whereHas('mataPelajaran', function($q) use ($jenjangId) {
            $q->where('id_jenjang', $jenjangId);
        });
    }

    public function scopeByKelas($query, $kelasId)
    {
        return $query->whereHas('materi', function($q) use ($kelasId) {
            $q->where('id_kelas', $kelasId);
        });
    }

    // Static Methods
    public static function getNextUrutan($kurikulumId, $mataPelajaranId)
    {
        return static::where('id_kurikulum', $kurikulumId)
            ->where('id_mata_pelajaran', $mataPelajaranId)
            ->max('urutan') + 1;
    }

    public static function reorderUrutan($kurikulumId, $mataPelajaranId)
    {
        $items = static::where('id_kurikulum', $kurikulumId)
            ->where('id_mata_pelajaran', $mataPelajaranId)
            ->orderBy('urutan')
            ->get();

        foreach ($items as $index => $item) {
            $item->update(['urutan' => $index + 1]);
        }
    }

    public static function getStatisticsByKurikulum($kurikulumId)
    {
        $stats = static::where('id_kurikulum', $kurikulumId)
            ->selectRaw('
                COUNT(DISTINCT id_mata_pelajaran) as total_mata_pelajaran,
                COUNT(*) as total_materi
            ')
            ->first();

        $jenjangCount = static::where('id_kurikulum', $kurikulumId)
            ->join('mata_pelajaran', 'kurikulum_materi.id_mata_pelajaran', '=', 'mata_pelajaran.id_mata_pelajaran')
            ->distinct('mata_pelajaran.id_jenjang')
            ->count();

        $kelasCount = static::where('id_kurikulum', $kurikulumId)
            ->join('materi', 'kurikulum_materi.id_materi', '=', 'materi.id_materi')
            ->distinct('materi.id_kelas')
            ->count();

        return [
            'total_mata_pelajaran' => $stats->total_mata_pelajaran ?? 0,
            'total_materi' => $stats->total_materi ?? 0,
            'total_jenjang' => $jenjangCount,
            'total_kelas' => $kelasCount
        ];
    }

    // Instance Methods
    public function moveUp()
    {
        $previous = static::where('id_kurikulum', $this->id_kurikulum)
            ->where('id_mata_pelajaran', $this->id_mata_pelajaran)
            ->where('urutan', '<', $this->urutan)
            ->orderBy('urutan', 'desc')
            ->first();

        if ($previous) {
            $tempUrutan = $this->urutan;
            $this->update(['urutan' => $previous->urutan]);
            $previous->update(['urutan' => $tempUrutan]);
            return true;
        }

        return false;
    }

    public function moveDown()
    {
        $next = static::where('id_kurikulum', $this->id_kurikulum)
            ->where('id_mata_pelajaran', $this->id_mata_pelajaran)
            ->where('urutan', '>', $this->urutan)
            ->orderBy('urutan', 'asc')
            ->first();

        if ($next) {
            $tempUrutan = $this->urutan;
            $this->update(['urutan' => $next->urutan]);
            $next->update(['urutan' => $tempUrutan]);
            return true;
        }

        return false;
    }

    public function isFirst()
    {
        return static::where('id_kurikulum', $this->id_kurikulum)
            ->where('id_mata_pelajaran', $this->id_mata_pelajaran)
            ->where('urutan', '<', $this->urutan)
            ->doesntExist();
    }

    public function isLast()
    {
        return static::where('id_kurikulum', $this->id_kurikulum)
            ->where('id_mata_pelajaran', $this->id_mata_pelajaran)
            ->where('urutan', '>', $this->urutan)
            ->doesntExist();
    }

    public function canBeDeleted()
    {
        return true;
    }

    // Accessors
    public function getFullPathAttribute()
    {
        if (!$this->relationLoaded('mataPelajaran') || !$this->relationLoaded('materi')) {
            $this->load(['mataPelajaran.jenjang', 'materi.kelas']);
        }

        $jenjang = $this->mataPelajaran->jenjang->nama_jenjang ?? 'N/A';
        $mataPelajaran = $this->mataPelajaran->nama_mata_pelajaran ?? 'N/A';
        $kelas = $this->materi->kelas->display_name ?? 'N/A';
        $materi = $this->materi->nama_materi ?? 'N/A';

        return "{$jenjang} > {$mataPelajaran} > {$kelas} > {$materi}";
    }

    public function getHierarchyAttribute()
    {
        if (!$this->relationLoaded('mataPelajaran') || !$this->relationLoaded('materi')) {
            $this->load(['mataPelajaran.jenjang', 'materi.kelas']);
        }

        return [
            'jenjang' => $this->mataPelajaran->jenjang ?? null,
            'mata_pelajaran' => $this->mataPelajaran ?? null,
            'kelas' => $this->materi->kelas ?? null,
            'materi' => $this->materi ?? null
        ];
    }

    // Events
    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($kurikulumMateri) {
            static::reorderUrutan($kurikulumMateri->id_kurikulum, $kurikulumMateri->id_mata_pelajaran);
        });
    }
}