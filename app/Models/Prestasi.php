<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Prestasi extends Model
{
    use HasFactory;
    protected $table = 'prestasi';
    protected $primaryKey = 'id_prestasi';

    protected $fillable = [
        'id_prestasi', 
        'id_anak', 
        'jenis_prestasi', 
        'level_prestasi', 
        'nama_prestasi',
        'foto',
        'tgl_upload',
        'is_read'
    ];

    protected $appends = ['foto_url'];

    public function getFotoUrlAttribute()
    {
        if ($this->foto) {
            return url("storage/Prestasi/{$this->id_anak}/{$this->foto}");
        }
        
        return null;
    }

    public function anak()
    {
        return $this->belongsTo(Anak::class, 'id_anak');
    }
}