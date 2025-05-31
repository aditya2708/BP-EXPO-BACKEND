<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SuratAb extends Model
{
    use HasFactory;

    protected $table = 'surat_ab'; // Nama tabel
    protected $primaryKey = 'id_surat'; // Primary key
    protected $fillable = [
        'id_anak', 
        'pesan', 
        'foto', 
        'tanggal', 
        'is_read'
    ];

    /**
     * Relasi ke tabel Anak.
     */
    public function anak()
    {
        return $this->belongsTo(Anak::class, 'id_anak');
    }
}
