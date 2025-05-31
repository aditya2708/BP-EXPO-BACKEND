<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AnakPendidikan extends Model
{
    use HasFactory;

    protected $table = 'anak_pend'; // Nama tabel

    protected $primaryKey = 'id_anak_pend'; // Primary key
    public $timestamps = true; // Jika tabel memiliki timestamps

    protected $fillable = [
        'id_keluarga',
        'jenjang',
        'kelas',
        'nama_sekolah',
        'alamat_sekolah',
        'jurusan',
        'semester',
        'nama_pt',
        'alamat_pt',
    ];

    /**
     * Relasi ke tabel keluarga.
     */
    public function keluarga()
    {
        return $this->belongsTo(Keluarga::class, 'id_keluarga');
    }

       

}
