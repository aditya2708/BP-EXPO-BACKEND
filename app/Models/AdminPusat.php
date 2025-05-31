<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdminPusat extends Model
{
    use HasFactory;

    // Nama tabel yang digunakan
    protected $table = 'admin_pusat';

    protected $primaryKey = 'id_admin_pusat'; 

    // Kolom yang dapat diisi secara massal
    protected $fillable = [
        'id_users', // Gunakan 'id_users' sebagai kolom yang diisi
        'nama_lengkap',
        'alamat',
        'no_hp',
        'foto',
    ];

    /**
     * Relasi ke model User.
     * Setiap admin pusat memiliki satu user terkait.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'id_users', 'id_users'); // Menggunakan 'id_users' untuk relasi
    }
}
