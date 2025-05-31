<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Materi extends Model
{
    use HasFactory;

    protected $table = 'materi'; // Nama tabel yang sesuai dengan migration

    protected $primaryKey = 'id_materi'; // Menyesuaikan dengan nama primary key

    protected $fillable = [
        'id_level_anak_binaan',
        'mata_pelajaran',
        'nama_materi',
    ];

    /**
     * Relasi ke model LevelAnakBinaan
     */
    public function levelAnakBinaan()
    {
        return $this->belongsTo(LevelAnakBinaan::class, 'id_level_anak_binaan');
    }
}
