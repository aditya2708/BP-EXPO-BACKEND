<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdminCabang extends Model
{
    use HasFactory;

    protected $table = 'admin_cabang'; // Nama tabel

    protected $primaryKey = 'id_admin_cabang'; 

    protected $fillable = [
        'user_id', 
        'id_kacab', 
        'nama_lengkap', 
        'alamat', 
        'no_hp', 
        'foto'
    ];

    /**
     * Relasi ke model User.
     * Setiap admin cabang memiliki satu user terkait.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id_users');  // Relasi dengan model User
    }

    /**
     * Relasi ke model Kacab.
     * Setiap admin cabang terhubung dengan satu cabang.
     */
    public function kacab()
    {
        return $this->belongsTo(Kacab::class, 'id_kacab');
    }
}
