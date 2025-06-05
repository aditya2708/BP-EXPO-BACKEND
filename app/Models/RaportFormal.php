<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RaportFormal extends Model
{
    use HasFactory;

    protected $table = 'raport_formal';
    protected $primaryKey = 'id_raport_formal';

    protected $fillable = [
        'id_anak',
        'nama_sekolah',
        'tingkat_sekolah',
        'kelas',
        'jurusan',
        'semester',
        'tahun_ajaran',
        'file_raport',
        'file_transkrip'
    ];

    protected $appends = ['file_raport_url', 'file_transkrip_url'];

    public function anak()
    {
        return $this->belongsTo(Anak::class, 'id_anak', 'id_anak');
    }

    public function getFileRaportUrlAttribute()
    {
        if ($this->file_raport) {
            return url("storage/RaportFormal/{$this->id_anak}/{$this->file_raport}");
        }
        return null;
    }

    public function getFileTranskripUrlAttribute()
    {
        if ($this->file_transkrip) {
            return url("storage/RaportFormal/{$this->id_anak}/{$this->file_transkrip}");
        }
        return null;
    }
}