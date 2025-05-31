<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Wali extends Model
{
    use HasFactory;

    protected $table = 'wali'; // Nama tabel

    protected $primaryKey = 'id_wali'; // Primary key

    protected $fillable = [
        'id_keluarga',
        'nik_wali',
        'nama_wali',
        'agama',
        'tempat_lahir',
        'tanggal_lahir',
        'alamat',
        'id_prov',
        'id_kab',
        'id_kec',
        'id_kel',
        'penghasilan',
        'hub_kerabat',
    ];

    /**
     * Relasi ke tabel Keluarga.
     */
    public function keluarga()
    {
        return $this->belongsTo(Keluarga::class, 'id_keluarga');
    }

    public function kabupaten()
    {
        return $this->belongsTo(Kabupaten::class, 'id_kab', 'id_kab'); // Kolom foreign key dan primary key yang benar
    }

    public function kecamatan()
    {
        return $this->belongsTo(Kecamatan::class, 'id_kec', 'id_kec'); // Kolom foreign key dan primary key yang benar
    }

    public function kelurahan()
    {
        return $this->belongsTo(Kelurahan::class, 'id_kel', 'id_kel'); // Kolom foreign key dan primary key yang benar
    }

    public function provinsi()
    {
        return $this->belongsTo(Provinsi::class, 'id_prov', 'id_prov'); // Kolom foreign key dan primary key yang benar
    }
}
