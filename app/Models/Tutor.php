<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tutor extends Model
{
    use HasFactory;

    protected $table = 'tutor';
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
        'jenis_tutor',
        'foto'
    ];

    protected $appends = ['foto_url'];

    public function getFotoUrlAttribute()
    {
        if ($this->foto) {
            return url("storage/Tutor/{$this->id_tutor}/{$this->foto}");
        }
        
        return url('images/default.png');
    }

    public function kacab() {
        return $this->belongsTo(Kacab::class, 'id_kacab');
    }

    public function wilbin() {
        return $this->belongsTo(Wilbin::class, 'id_wilbin');
    }

    public function shelter() {
        return $this->belongsTo(Shelter::class, 'id_shelter');
    }

    public function absenUser()
    {
        return $this->hasMany(AbsenUser::class, 'id_tutor');
    }

    public function competencies()
    {
        return $this->hasMany(TutorCompetency::class, 'id_tutor');
    }
}