<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Semester extends Model
{
    use HasFactory;

    protected $table = 'semester';
    protected $primaryKey = 'id_semester';
    
    protected $fillable = [
        'nama_semester',
        'tahun_ajaran',
        'periode',
        'tanggal_mulai',
        'tanggal_selesai',
        'is_active'
    ];

    protected $casts = [
        'tanggal_mulai' => 'date',
        'tanggal_selesai' => 'date',
        'is_active' => 'boolean'
    ];

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByTahunAjaran($query, $tahunAjaran)
    {
        return $query->where('tahun_ajaran', $tahunAjaran);
    }

    // Relations
    public function penilaian()
    {
        return $this->hasMany(Penilaian::class, 'id_semester');
    }

    public function nilaiSikap()
    {
        return $this->hasMany(NilaiSikap::class, 'id_semester');
    }

    public function raport()
    {
        return $this->hasMany(Raport::class, 'id_semester');
    }

    // Methods
    public function setAsActive()
    {
        Semester::where('is_active', true)->update(['is_active' => false]);
        $this->update(['is_active' => true]);
    }

    public function getFullNameAttribute()
    {
        return $this->nama_semester . ' ' . $this->tahun_ajaran;
    }
}