<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JenisKompetensi extends Model
{
    use HasFactory;

    protected $table = 'jenis_kompetensi';
    protected $primaryKey = 'id_jenis_kompetensi';

    protected $fillable = [
        'nama_jenis_kompetensi',
        'deskripsi',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean'
    ];

    public function tutorCompetencies()
    {
        return $this->hasMany(TutorCompetency::class, 'id_jenis_kompetensi');
    }
}