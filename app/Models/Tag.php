<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Tag extends Model
{
    use HasFactory;

    // Tabel yang digunakan
    protected $table = 'tags';

    // Kolom yang dapat diisi (fillable)
    protected $fillable = [
        'nama',
        'link'
    ];

    // Relasi many-to-many: sebuah tag bisa dipakai oleh banyak berita
    public function berita()
    {
        return $this->belongsToMany(Berita::class, 'berita_tag', 'tag_id', 'id_berita')->withTimestamps();
    }
}
