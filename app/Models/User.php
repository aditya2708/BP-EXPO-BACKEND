<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $primaryKey = 'id_users'; 

    protected $fillable = [
        'username',
        'email',
        'password',
        'level', // Level fleksibel, misalnya 'admin', 'user', dll.
        'status',
        // 'remember_token',
        'token_api',
        'token'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    /**
     * Relasi ke admin_pusat.
     */
    public function adminPusat()
    {
        return $this->hasOne(AdminPusat::class, 'id_users', 'id_users'); // Relasi dengan id_users
    }

   // Di dalam model User
    public function adminCabang() {
        return $this->hasOne(AdminCabang::class, 'user_id', 'id_users');
    }


    public function adminShelter()
    {
        return $this->hasOne(AdminShelter::class, 'user_id');
    }

    // Di model User.php
    public function donatur()
    {
        return $this->hasOne(Donatur::class, 'id_users', 'id_users');
    }


}
