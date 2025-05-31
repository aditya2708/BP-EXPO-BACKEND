<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AnakDetail extends Model
{
    use HasFactory;

    protected $table = 'anak_detail'; // Nama tabel

    protected $primaryKey = 'id_anak_detail'; // Primary key

    protected $fillable = [
        'id_quran',
        'tahfidz',
    ];

    /**
     * Relasi ke tabel al_quran.
     */
    public function alQuran()
    {
        return $this->belongsTo(AlQuran::class, 'id_quran');
    }
}
