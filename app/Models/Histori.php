<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Histori extends Model
{
    use HasFactory;

    // Nama tabel yang digunakan
    protected $table = 'histori';

    // Primary key yang digunakan
    protected $primaryKey = 'id_histori';

    // Kolom yang bisa diisi mass-assignment
    protected $fillable = [
        'id_anak',
        'jenis_histori',
        'nama_histori',
        'di_opname',
        'dirawat_id',
        'tanggal',
        'foto',
        'is_read',
    ];

    // Relasi ke model Anak
    public function anak()
    {
        return $this->belongsTo(Anak::class, 'id_anak');
    }
    
     // Relasi ke model Anak (yang dirawat)
    // public function dirawatAnak()
    // {
    //     return $this->belongsTo(Anak::class, 'dirawat_id');
    // }
    
}
