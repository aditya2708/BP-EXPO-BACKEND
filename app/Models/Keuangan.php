<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Keuangan extends Model
{
    use HasFactory;

    protected $table = 'keuangan'; // Nama tabel
    protected $primaryKey = 'id_keuangan'; // Primary key
    protected $fillable = [
        'id_anak', 
        'tingkat_sekolah', 
        'semester', 
        'bimbel', 
        'eskul_dan_keagamaan', 
        'laporan', 
        'uang_tunai', 
        'donasi', 
        'subsidi_infak'
    ];

    /**
     * Relasi ke tabel Anak.
     */
    public function anak()
    {
        return $this->belongsTo(Anak::class, 'id_anak');
    }
}
