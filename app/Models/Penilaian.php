<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Penilaian extends Model
{
    use HasFactory;

    protected $table = 'penilaian';
    protected $primaryKey = 'id_penilaian';
    
    protected $fillable = [
        'id_anak',
        'id_aktivitas',
        'id_materi',
        'id_jenis_penilaian',
        'id_semester',
        'nilai',
        'deskripsi_tugas',
        'tanggal_penilaian',
        'catatan'
    ];

    protected $casts = [
        'nilai' => 'decimal:2',
        'tanggal_penilaian' => 'date'
    ];

    // Relations
    public function anak()
    {
        return $this->belongsTo(Anak::class, 'id_anak');
    }

    public function aktivitas()
    {
        return $this->belongsTo(Aktivitas::class, 'id_aktivitas');
    }

    public function materi()
    {
        return $this->belongsTo(Materi::class, 'id_materi');
    }

    public function jenisPenilaian()
    {
        return $this->belongsTo(JenisPenilaian::class, 'id_jenis_penilaian');
    }

    public function semester()
    {
        return $this->belongsTo(Semester::class, 'id_semester');
    }

    // Scopes
    public function scopeByAnak($query, $idAnak)
    {
        return $query->where('id_anak', $idAnak);
    }

    public function scopeBySemester($query, $idSemester)
    {
        return $query->where('id_semester', $idSemester);
    }

    public function scopeByMateri($query, $idMateri)
    {
        return $query->where('id_materi', $idMateri);
    }

    // Methods
    public function getNilaiHurufAttribute()
    {
        if ($this->nilai >= 90) return 'A';
        if ($this->nilai >= 80) return 'B';
        if ($this->nilai >= 70) return 'C';
        if ($this->nilai >= 60) return 'D';
        return 'E';
    }

    public function getPredikatAttribute()
    {
        if ($this->nilai >= 90) return 'Sangat Baik';
        if ($this->nilai >= 80) return 'Baik';
        if ($this->nilai >= 70) return 'Cukup';
        if ($this->nilai >= 60) return 'Kurang';
        return 'Sangat Kurang';
    }
}