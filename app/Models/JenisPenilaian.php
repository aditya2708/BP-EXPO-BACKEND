<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JenisPenilaian extends Model
{
    use HasFactory;

    protected $table = 'jenis_penilaian';
    protected $primaryKey = 'id_jenis_penilaian';
    
    protected $fillable = [
        'nama_jenis',
        'bobot_persen',
        'kategori'
    ];

    protected $casts = [
        'bobot_persen' => 'decimal:2'
    ];

    const KATEGORI_TUGAS = 'tugas';
    const KATEGORI_ULANGAN = 'ulangan';
    const KATEGORI_UJIAN = 'ujian';

    // Scopes
    public function scopeByKategori($query, $kategori)
    {
        return $query->where('kategori', $kategori);
    }

    // Relations
    public function penilaian()
    {
        return $this->hasMany(Penilaian::class, 'id_jenis_penilaian');
    }

    // Methods
    public function getBobotDecimalAttribute()
    {
        return $this->bobot_persen / 100;
    }
}