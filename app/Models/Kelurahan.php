<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Kelurahan extends Model
{
    use HasFactory;

    protected $table = 'kelurahan'; // Nama tabel

    protected $fillable = ['id_kec', 'nama']; // Kolom yang bisa diisi

    public function kecamatan()
    {
        return $this->belongsTo(Kecamatan::class, 'id_kec');
    }
}
