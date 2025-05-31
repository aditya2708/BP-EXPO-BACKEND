<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class KomentarBerita extends Model
{
    use HasFactory;

    protected $table = 'komentar_berita';
    protected $primaryKey = 'id_komentar';

    protected $fillable = [
        'id_berita',
        'nama_pengirim',
        'isi_komentar',
        'parent_id',
        'status_komentar',
        'likes_komentar',
    ];

    // Constants untuk status komentar
    const STATUS_AKTIF = 'Aktif';
    const STATUS_TIDAK_AKTIF = 'Tidak Aktif';

    /**
     * Relasi ke berita
     */
    public function berita()
    {
        return $this->belongsTo(Berita::class, 'id_berita', 'id_berita');
    }

    /**
     * Relasi ke komentar induk (jika ini adalah balasan)
     */
    public function parent()
    {
        return $this->belongsTo(KomentarBerita::class, 'parent_id');
    }

    /**
     * Relasi ke balasan-balasan komentar ini
     */
    public function replies()
    {
        return $this->hasMany(KomentarBerita::class, 'parent_id');
    }

    /**
     * Accessor untuk status_komentar (nilai default)
     */
    public function getStatusKomentarAttribute($value)
    {
        return $value ?? self::STATUS_AKTIF;
    }

    /**
     * Mutator untuk validasi status komentar
     */
    public function setStatusKomentarAttribute($value)
    {
        $this->attributes['status_komentar'] = in_array($value, [self::STATUS_AKTIF, self::STATUS_TIDAK_AKTIF])
            ? $value
            : self::STATUS_TIDAK_AKTIF;
    }
}
