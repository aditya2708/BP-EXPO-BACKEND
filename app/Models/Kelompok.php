<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Kelompok extends Model
{
    use HasFactory;

    protected $table = 'kelompok'; // Nama tabel

    protected $primaryKey = 'id_kelompok'; // Primary key

    protected $fillable = [
        'id_shelter',
        'id_level_anak_binaan',
        'nama_kelompok',
        'jumlah_anggota'
    ];

    /**
     * Relasi ke tabel Shelter.
     */
    public function shelter()
    {
        return $this->belongsTo(Shelter::class, 'id_shelter');
    }

    /**
     * Relasi ke tabel LevelAnakBinaan.
     */
    public function levelAnakBinaan()
    {
        return $this->belongsTo(LevelAnakBinaan::class, 'id_level_anak_binaan');
    }

    public function anak()
    {
        return $this->hasMany(Anak::class, 'id_kelompok');
    }
    
//     public function levelAnakBinaan()
// {
//     return $this->belongsTo(LevelAnakBinaan::class, 'id_level_anak_binaan', 'id_level_anak_binaan');
// }


}
