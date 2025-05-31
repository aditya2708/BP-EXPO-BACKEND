<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Kecamatan extends Model
{
    use HasFactory;

    protected $table = 'kecamatan'; // Nama tabel

    protected $fillable = ['id_kab', 'nama']; // Kolom yang bisa diisi

    public function kabupaten()
    {
        return $this->belongsTo(Kabupaten::class, 'id_kab');
    }
}
