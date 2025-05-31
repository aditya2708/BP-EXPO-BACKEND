<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RaportDetail extends Model
{
    use HasFactory;

    protected $table = 'raport_detail';
    protected $primaryKey = 'id_raport_detail';
    
    protected $fillable = [
        'id_raport',
        'mata_pelajaran',
        'nilai_akhir',
        'nilai_huruf',
        'kkm',
        'keterangan'
    ];

    protected $casts = [
        'nilai_akhir' => 'decimal:2',
        'kkm' => 'decimal:2'
    ];

    // Relations
    public function raport()
    {
        return $this->belongsTo(Raport::class, 'id_raport');
    }

    // Methods
    public function isTuntas()
    {
        return $this->nilai_akhir >= $this->kkm;
    }

    public function getStatusAttribute()
    {
        return $this->isTuntas() ? 'Tuntas' : 'Belum Tuntas';
    }

    public function getSelisihKkmAttribute()
    {
        return $this->nilai_akhir - $this->kkm;
    }
}