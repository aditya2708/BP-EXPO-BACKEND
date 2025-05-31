<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Kacab extends Model
{
    use HasFactory;

    protected $table = 'kacab';
    protected $primaryKey = 'id_kacab';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = ['nama_kacab', 'no_telpon', 'alamat', 'id_prov', 'id_kab', 'id_kec', 'id_kel'];
    
    public function wilbins()
    {
        return $this->hasMany(Wilbin::class, 'id_kacab');
    }

    public function tutors()
    {
        return $this->hasMany(Tutor::class, 'id_kacab');
    }

    public function provinsi()
    {
        return $this->belongsTo(Provinsi::class, 'id_prov', 'id_prov');
    }

    public function kabupaten()
    {
        return $this->belongsTo(Kabupaten::class, 'id_kab', 'id_kab');
    }

    public function kecamatan()
    {
        return $this->belongsTo(Kecamatan::class, 'id_kec', 'id_kec');
    }

    public function kelurahan()
    {
        return $this->belongsTo(Kelurahan::class, 'id_kel', 'id_kel');
    }

    // Jika ada relasi dengan User
    public function user()
    {
        return $this->belongsTo(User::class, 'id_user'); // Pastikan nama kolom sesuai yang ada di tabel
    }
}
