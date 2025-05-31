<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Kabupaten extends Model
{
    use HasFactory;

    protected $table = 'kabupaten'; // Nama tabel

    protected $fillable = ['id_prov', 'id_jenis', 'nama']; // Kolom yang bisa diisi

    public function provinsi()
    {
        return $this->belongsTo(Provinsi::class, 'id_prov');
    }

    public function jenis()
    {
        return $this->belongsTo(Jenis::class, 'id_jenis');
    }
}
