<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Wilbin extends Model
{
    use HasFactory;

    protected $table = 'wilbin'; // Nama tabel
    protected $primaryKey = 'id_wilbin'; 

    protected $fillable = ['nama_wilbin', 'id_kacab']; 

    /**
     * Relasi ke tabel kacab.
     */
    public function kacab()
    {
        return $this->belongsTo(Kacab::class, 'id_kacab');
    }

    /**
     * Relasi ke tabel shelter.
     * Define a relationship to get the shelters associated with this Wilayah Binaan.
     */
    public function shelters()
    {
        return $this->hasMany(Shelter::class, 'id_wilbin');
    }
}
