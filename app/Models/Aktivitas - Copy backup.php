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
        'jenis_kegiatan',
        'level',
        'nama_kelompok',
        'materi',
        'foto_1',
        'foto_2',
        'foto_3',
        'tanggal',
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
}