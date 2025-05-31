<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Berita extends Model
{
    use HasFactory;

    protected $table = 'berita';

    // Menentukan primary key sesuai dengan skema yang diberikan
    protected $primaryKey = 'id_berita';

    // Kolom yang dapat diisi (fillable)
    protected $fillable = [
        'judul',
        'foto',
        'foto2',
        'foto3',
        'konten',
        'tanggal',
        'views_berita',
        'status_berita',
        'id_kategori_berita',
        'likes_berita'
    ];
    
    const STATUS_AKTIF = 'Aktif';
    const STATUS_NON_AKTIF = 'Tidak Aktif';

    // Getter: Menampilkan status berita dengan format yang lebih jelas
    public function getStatusBeritaAttribute($value)
    {
        // Jika status_berita NULL, set ke 'Aktif'
        return $value === null ? self::STATUS_AKTIF : ($value === self::STATUS_AKTIF ? 'Aktif' : 'Tidak Aktif');
    }


    // Setter: Memastikan hanya nilai yang valid yang disimpan di database
    public function setStatusBeritaAttribute($value)
    {
        $this->attributes['status_berita'] = in_array($value, [self::STATUS_AKTIF, self::STATUS_NON_AKTIF]) 
            ? $value 
            : self::STATUS_NON_AKTIF;
    }
    
     public function kategori()
    {
        return $this->belongsTo(KategoriBerita::class, 'id_kategori_berita', 'id');
    }
    
    public function komentar()
    {
        return $this->hasMany(KomentarBerita::class, 'id_berita', 'id_berita');
    }
    
     public function tags()
    {
        return $this->belongsToMany(Tag::class, 'berita_tag', 'id_berita', 'tag_id')->withTimestamps();
    }
    
    // public static function incrementViews($judul)
    // {
    //     // Cari berita berdasarkan ID
    //     $berita = self::find($judul);
    
    //     // Jika berita ditemukan, increment views_berita
    //     if ($berita) {
    //         $berita->increment('views_berita');
    //         return $berita;
    //     }
    
    //     return null;
    // }
    
    public static function incrementViews($judul)
    {
        // Cari berita berdasarkan judul
        $berita = self::where('judul', $judul)->first();
        
        // Jika berita ditemukan, increment views_berita
        if ($berita) {
            $berita->increment('views_berita');
            return $berita;
        }
        
        return null;
    }


    public function getFotoFolderAttribute()
    {
        $folderPath = "berita/{$this->id_berita}";

        if (Storage::exists($folderPath)) {
            return collect(Storage::files($folderPath))
                ->filter(fn($file) => in_array(pathinfo($file, PATHINFO_EXTENSION), ['jpg', 'png', 'jpeg']))
                ->map(fn($file) => asset('storage/' . $file));
        }

        return collect();
    }

    public function getFotoUrlAttribute()
    {
        return $this->foto ? asset("storage/berita/{$this->id_berita}/{$this->foto}") : null;
    }
    
    public function getFoto2UrlAttribute()
    {
        return $this->foto2 ? asset("storage/berita/{$this->id_berita}/{$this->foto2}") : null;
    }
    
    public function getFoto3UrlAttribute()
    {
        return $this->foto3 ? asset("storage/berita/{$this->id_berita}/{$this->foto3}") : null;
    }
    
   


}
