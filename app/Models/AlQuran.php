<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AlQuran extends Model
{
    use HasFactory;

    protected $table = 'al_quran'; // Nama tabel

    protected $primaryKey = 'id_quran'; // Primary key

    protected $fillable = [
        'nama',
        'ayat',
        'keterangan',
    ];

    /**
     * Relasi ke tabel anak_detail.
     */
    public function anakDetails()
    {
        return $this->hasMany(AnakDetail::class, 'id_quran');
    }
}
