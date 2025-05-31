<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FotoRapor extends Model
{
    use HasFactory;

    protected $table = 'foto_rapor';
    protected $primaryKey = 'id_foto'; // Pastikan primary key sesuai
    public $incrementing = true; // Pastikan incrementing untuk ID
    protected $keyType = 'int'; // Default tipe data ID

    protected $fillable = [
        'id_rapor',
        'nama',
    ];

    public function raport()
    {
        return $this->belongsTo(Raport::class, 'id_rapor', 'id_raport');
    }
}
