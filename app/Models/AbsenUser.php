<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AbsenUser extends Model
{
    use HasFactory;

    protected $table = 'absen_user'; // Nama tabel
    protected $primaryKey = 'id_absen_user'; // Primary key
    protected $fillable = ['id_anak', 'id_tutor']; // Kolom yang bisa diisi

    /**
     * Relasi ke tabel Anak.
     */
    public function anak()
    {
        return $this->belongsTo(Anak::class, 'id_anak');
    }

    /**
     * Relasi ke tabel Tutor.
     */
    public function tutor()
    {
        return $this->belongsTo(Tutor::class, 'id_tutor');
    }

     // Relasi ke tabel Absen
     public function absen() {
        return $this->hasMany(Absen::class, 'id_absen_user');
    }
}
