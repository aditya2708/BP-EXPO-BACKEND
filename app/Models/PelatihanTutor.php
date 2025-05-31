<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PelatihanTutor extends Model
{
    use HasFactory;
    protected $table = 'pelatihan_tutor';
    protected $primaryKey = 'id_pelatihan';

    protected $fillable = [
        'id_pelatihan', 
        'id_tutor', 
        'nama_pelatihan', 
        'tingkat_pelatihan', 
        'jenis_pelatihan', 
        'tanggal',
        'foto'
    ];

    public function tutor()
    {
        return $this->belongsTo(Tutor::class, 'id_tutor');
    }
}
