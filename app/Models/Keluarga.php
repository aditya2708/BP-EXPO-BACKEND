<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Keluarga extends Model
{
    use HasFactory;

    protected $table = 'keluarga'; // Nama tabel

    protected $primaryKey = 'id_keluarga'; // Primary key

    protected $fillable = [
        'id_kacab',
        'id_wilbin',
        'id_shelter',
        'no_kk',
        'kepala_keluarga',
        'status_ortu',
        'id_bank',
        'no_rek',
        'an_rek',
        'no_telp',
        'an_tlp',
    ];

    /**
     * Relasi ke tabel kacab.
     */
        public function kacab()
    {
        return $this->belongsTo(Kacab::class, 'id_kacab', 'id_kacab');
    }



    /**
     * Relasi ke tabel wilbin.
     */
    public function wilbin()
    {
        return $this->belongsTo(Wilbin::class, 'id_wilbin');
    }

    /**
     * Relasi ke tabel shelter.
     */
    public function shelter()
    {
        return $this->belongsTo(Shelter::class, 'id_shelter');
    }

    /**
     * Relasi ke tabel bank.
     */
    public function bank()
    {
        return $this->belongsTo(Bank::class, 'id_bank');
    }

    public function ayah()
    {
        return $this->hasOne(Ayah::class, 'id_keluarga', 'id_keluarga');
    }

    public function ibu()
    {
        return $this->hasOne(Ibu::class, 'id_keluarga', 'id_keluarga');
    }

    public function wali()
    {
        return $this->hasOne(Wali::class, 'id_keluarga');
    }

    public function surveys()
    {
        return $this->hasMany(Survey::class, 'id_keluarga');
    }



}
