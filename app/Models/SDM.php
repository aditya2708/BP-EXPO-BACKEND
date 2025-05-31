<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SDM extends Model
{
    use HasFactory;

    protected $table = 'sdm';
    protected $primaryKey = 'id_sdm';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'nik', 'nama', 'jenis_kelamin', 'alamat', 'pendidikan', 'tgl_gabung', 'tgl_keluar', 
        'no_telp', 'email', 'keaktifan_edu', 'keterangan', 'id_prov', 'id_kab', 'id_kec', 
        'id_kel', 'id_struktur', 'id_wilbin', 'id_kacab'
    ];

    public function provinsi()
    {
        return $this->belongsTo(Provinsi::class, 'id_prov', 'id_prov');
    }

    public function kabupaten()
    {
        return $this->belongsTo(Kabupaten::class, 'id_kab', 'id_kab');
    }

    public function kecamatan()
    {
        return $this->belongsTo(Kecamatan::class, 'id_kec', 'id_kec');
    }

    public function kelurahan()
    {
        return $this->belongsTo(Kelurahan::class, 'id_kel', 'id_kel');
    }

    public function struktur()
    {
        return $this->belongsTo(Struktur::class, 'id_struktur');
    }

    public function wilbin()
    {
        return $this->belongsTo(Wilbin::class, 'id_wilbin');
    }

    public function kacab()
    {
        return $this->belongsTo(Kacab::class, 'id_kacab');
    }
}
