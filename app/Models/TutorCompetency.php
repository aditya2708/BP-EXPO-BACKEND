<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TutorCompetency extends Model
{
    use HasFactory;

    protected $table = 'tutor_competencies';
    protected $primaryKey = 'id_competency';

    protected $fillable = [
        'id_tutor',
        'id_jenis_kompetensi',
        'nama_competency',
        'tanggal_diperoleh',
        'tanggal_kadaluarsa',
        'instansi_penerbit',
        'nomor_sertifikat',
        'file_sertifikat',
        'deskripsi'
    ];

    protected $casts = [
        'tanggal_diperoleh' => 'date',
        'tanggal_kadaluarsa' => 'date'
    ];

    protected $appends = ['file_url'];

    public function getFileUrlAttribute()
    {
        if ($this->file_sertifikat) {
            return url("storage/TutorCompetency/{$this->id_tutor}/{$this->file_sertifikat}");
        }
        return null;
    }

    public function tutor()
    {
        return $this->belongsTo(Tutor::class, 'id_tutor');
    }

    public function jenisKompetensi()
    {
        return $this->belongsTo(JenisKompetensi::class, 'id_jenis_kompetensi');
    }
}