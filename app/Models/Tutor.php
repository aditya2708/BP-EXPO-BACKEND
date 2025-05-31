<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tutor extends Model
{
    use HasFactory;

    protected $table = 'tutor'; // Nama tabel
    protected $primaryKey = 'id_tutor'; 
    
    protected $fillable = [
        'nama', 
        'pendidikan', 
        'alamat', 
        'email', 
        'no_hp', 
        'id_kacab', 
        'id_wilbin', 
        'id_shelter', 
        'maple', 
        'foto'
    ]; 

    // Add this to ensure foto_url is included in JSON responses
    protected $appends = ['foto_url'];

    // Updated foto URL accessor to match Tutor model's storage path
    public function getFotoUrlAttribute()
    {
        if ($this->foto) {
            return url("storage/Tutor/{$this->id_tutor}/{$this->foto}");
        }
        
        return url('images/default.png');
    }

    // Relasi ke tabel kacab.
    public function kacab() {
        return $this->belongsTo(Kacab::class, 'id_kacab');
    }

    // Relasi ke tabel wilbin.
    public function wilbin() {
        return $this->belongsTo(Wilbin::class, 'id_wilbin');
    }

    // Relasi ke tabel shelter.
    public function shelter() {
        return $this->belongsTo(Shelter::class, 'id_shelter');
    }

    public function absenUser()
    {
        return $this->hasMany(AbsenUser::class, 'id_tutor');
    }
}