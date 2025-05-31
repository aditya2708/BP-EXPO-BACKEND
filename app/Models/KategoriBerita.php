<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KategoriBerita extends Model
{
    use HasFactory;

    protected $table = 'kategori_berita';

    protected $primaryKey = 'id';

    public $timestamps = true; // Aktifkan timestamps karena sudah ada di SQL

    protected $fillable = [
        'name_kategori',
        'status_kategori_berita'
    ];
    
    const STATUS_AKTIF = 'Aktif';
    const STATUS_NON_AKTIF = 'Tidak Aktif';

    // Getter: Menampilkan status berita dengan format yang lebih jelas
    public function getStatusKategoriBeritaAttribute($value)
    {
        // Jika status_berita NULL, set ke 'Aktif'
        return $value === null ? self::STATUS_AKTIF : ($value === self::STATUS_AKTIF ? 'Aktif' : 'Tidak Aktif');
    }


    // Setter: Memastikan hanya nilai yang valid yang disimpan di database
    public function setStatusKategoriBeritaAttribute($value)
    {
        $this->attributes['status_kategori_berita'] = in_array($value, [self::STATUS_AKTIF, self::STATUS_NON_AKTIF]) 
            ? $value 
            : self::STATUS_NON_AKTIF;
    }
    
     public function berita()
    {
        return $this->hasMany(Berita::class, 'id_kategori_berita', 'id');
    }
}
