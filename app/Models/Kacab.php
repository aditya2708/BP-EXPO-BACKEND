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

    protected $fillable = [
        'nama_kacab', 
        'no_telp', 
        'alamat', 
        'email',
        'id_prov', 
        'id_kab', 
        'id_kec', 
        'id_kel'
    ];

    protected $casts = [
        'id_kacab' => 'integer',
        'id_prov' => 'integer',
        'id_kab' => 'integer',
        'id_kec' => 'integer',
        'id_kel' => 'integer'
    ];

    // Geographic relationships
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

    // User relationships
    public function user()
    {
        return $this->belongsTo(User::class, 'id_user');
    }

    public function adminCabang()
    {
        return $this->hasMany(AdminCabang::class, 'id_kacab', 'id_kacab');
    }

    // Organizational relationships
    public function wilbins()
    {
        return $this->hasMany(Wilbin::class, 'id_kacab', 'id_kacab');
    }

    public function shelters()
    {
        return $this->hasManyThrough(Shelter::class, Wilbin::class, 'id_kacab', 'id_wilbin', 'id_kacab', 'id_wilbin');
    }

    public function tutors()
    {
        return $this->hasMany(Tutor::class, 'id_kacab', 'id_kacab');
    }

    public function donatur()
    {
        return $this->hasMany(Donatur::class, 'id_kacab', 'id_kacab');
    }

    // Academic relationships
    public function kurikulum()
    {
        return $this->hasMany(Kurikulum::class, 'id_kacab', 'id_kacab');
    }

    public function mataPelajaran()
    {
        return $this->hasMany(MataPelajaran::class, 'id_kacab', 'id_kacab');
    }

    public function jenjang()
    {
        return $this->hasMany(Jenjang::class, 'id_kacab', 'id_kacab');
    }

    public function kelas()
    {
        return $this->hasMany(Kelas::class, 'id_kacab', 'id_kacab');
    }

    public function materi()
    {
        return $this->hasMany(Materi::class, 'id_kacab', 'id_kacab');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'aktif');
    }

    public function scopeWithLocation($query)
    {
        return $query->with(['provinsi', 'kabupaten', 'kecamatan', 'kelurahan']);
    }

    public function scopeWithCounts($query)
    {
        return $query->withCount([
            'wilbins', 
            'shelters', 
            'tutors', 
            'donatur',
            'kurikulum',
            'mataPelajaran',
            'jenjang'
        ]);
    }

    // Accessors
    public function getNamaCabangAttribute()
    {
        return $this->nama_kacab;
    }

    public function getNoTelpAttribute($value)
    {
        return $value ?? $this->no_telpon; // Fallback untuk compatibility
    }

    public function getFullAddressAttribute()
    {
        $parts = array_filter([
            $this->alamat,
            $this->kelurahan?->nama_kelurahan,
            $this->kecamatan?->nama_kecamatan,
            $this->kabupaten?->nama_kabupaten,
            $this->provinsi?->nama_provinsi
        ]);
        
        return implode(', ', $parts);
    }

    // Methods
    public function getActiveKurikulum()
    {
        return $this->kurikulum()->where('is_active', true)->first();
    }

    public function getTotalShelters()
    {
        return $this->wilbins()->withCount('shelters')->get()->sum('shelters_count');
    }

    public function getKacabStats()
    {
        return [
            'total_wilbins' => $this->wilbins()->count(),
            'total_shelters' => $this->getTotalShelters(),
            'total_tutors' => $this->tutors()->count(),
            'total_donatur' => $this->donatur()->count(),
            'total_kurikulum' => $this->kurikulum()->count(),
            'active_kurikulum' => $this->kurikulum()->where('is_active', true)->count(),
            'total_mata_pelajaran' => $this->mataPelajaran()->count(),
            'total_jenjang' => $this->jenjang()->count()
        ];
    }
}