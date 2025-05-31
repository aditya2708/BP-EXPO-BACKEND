<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ayah extends Model
{
    use HasFactory;

    protected $table = 'ayah'; // Nama tabel

    protected $primaryKey = 'id_ayah'; // Primary key

    protected $fillable = [
        'id_keluarga',
        'nik_ayah',
        'nama_ayah',
        'agama',
        'tempat_lahir',
        'tanggal_lahir',
        'alamat',
        'id_prov',
        'id_kab',
        'id_kec',
        'id_kel',
        'penghasilan',
        'tanggal_kematian',
        'penyebab_kematian',
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
