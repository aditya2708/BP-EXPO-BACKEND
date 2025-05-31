<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdminShelter extends Model
{
    use HasFactory;

    protected $table = 'admin_shelter'; // The table name

    // Specify the primary key
    protected $primaryKey = 'id_admin_shelter'; 

    protected $fillable = [
        'user_id', 
        'id_kacab', 
        'id_wilbin', 
        'id_shelter', 
        'nama_lengkap', 
        'alamat_adm', 
        'no_hp', 
        'foto'
    ];

    // Accessor for the photo URL
    // public function getFotoUrlAttribute() {
    //     return $this->foto ? asset('storage/AdminShelter/Shelter/' . $this->foto) : asset('images/default.png'); 
    // }
    
      // Accessor untuk URL foto
    public function getFotoUrlAttribute()
    {
        return $this->foto ? asset("storage/AdminShelter/{$this->id_admin_shelter}/{$this->foto}") : null;
    }

    /**
     * Relationships to other models.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function kacab()
    {
        return $this->belongsTo(Kacab::class, 'id_kacab');
    }

    public function wilbin()
    {
        return $this->belongsTo(Wilbin::class, 'id_wilbin');
    }

    public function shelter()
    {
        return $this->belongsTo(Shelter::class, 'id_shelter');
    }
}
