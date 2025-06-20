<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Anak extends Model
{
    use HasFactory;

    protected $table = 'anak';

    protected $primaryKey = 'id_anak';

    const STATUS_AKTIF = ['aktif', 'Aktif'];
    const STATUS_TIDAK_AKTIF = ['tidak aktif', 'Tidak Aktif', 'non-aktif', 'Non-Aktif'];
    const STATUS_DITOLAK = ['Ditolak', 'ditolak'];
    const STATUS_DITANGGUHKAN = ['Ditangguhkan', 'ditangguhkan'];

    protected $attributes = [
        'status_validasi' => 'tidak aktif',
    ];
    
    protected $fillable = [
        'id_keluarga',
        'id_anak_pend',
        'id_kelompok',
        'id_shelter',
        'id_donatur',
        'id_level_anak_binaan',
        'nik_anak',
        'anak_ke',
        'dari_bersaudara',
        'nick_name',
        'full_name',
        'agama',
        'tempat_lahir',
        'tanggal_lahir',
        'jenis_kelamin',
        'tinggal_bersama',
        'status_validasi',
        'status_cpb',
        'jenis_anak_binaan',
        'hafalan',
        'pelajaran_favorit',
        'hobi',
        'prestasi',
        'jarak_rumah',
        'transportasi',
        'foto',
        'status',
    ];

    const STATUS_CPB_BCPB = 'BCPB';
    const STATUS_CPB_NPB = 'NPB';
    const STATUS_CPB_CPB = 'CPB';
    const STATUS_CPB_PB = 'PB';

    public function scopeAktif($query)
    {
        return $query->whereIn('status_validasi', self::STATUS_AKTIF);
    }

    public function scopeTidakAktif($query)
    {
        return $query->whereIn('status_validasi', self::STATUS_TIDAK_AKTIF);
    }

    protected static function boot()
    {
        parent::boot();
 
        static::saving(function ($anak) {
            $anak->updateStatusCpb();
        });
    }
 
    public function updateStatusCpb()
    {
        if ($this->status_cpb === self::STATUS_CPB_CPB || $this->status_cpb === self::STATUS_CPB_PB) {
            return;
        }
        
        if ($this->jenis_anak_binaan == 'BPCB') {
            $this->attributes['status_cpb'] = self::STATUS_CPB_BCPB;
        } elseif ($this->jenis_anak_binaan == 'NPB') {
            $this->attributes['status_cpb'] = self::STATUS_CPB_NPB;
        }
    }

    public function keluarga()
    {
        return $this->belongsTo(Keluarga::class, 'id_keluarga', 'id_keluarga');
    }

    public function anakPendidikan()
    {
        return $this->belongsTo(AnakPendidikan::class, 'id_anak_pend', 'id_anak_pend');
    }

    public function kelompok()
    {
        return $this->belongsTo(Kelompok::class, 'id_kelompok');
    }

    public function shelter()
    {
        return $this->belongsTo(Shelter::class, 'id_shelter', 'id_shelter');
    }

    public function donatur()
    {
        return $this->belongsTo(Donatur::class, 'id_donatur', 'id_donatur');
    }

    public function levelAnakBinaan()
    {
        return $this->belongsTo(LevelAnakBinaan::class, 'id_level_anak_binaan', 'id_level_anak_binaan');
    }

    public function suratAb()
    {
        return $this->hasMany(SuratAb::class, 'id_anak');
    }

    public function Raport()
    {
        return $this->hasMany(Raport::class, 'id_anak');
    }

    public function Histori()
    {
        return $this->hasMany(Histori::class, 'id_anak');
    }

    public function wilbin()
    {
        return $this->belongsTo(Wilbin::class, 'id_wilbin');
    }

    public function aktivitas()
    {
        return $this->hasMany(Aktivitas::class, 'id_anak', 'id_anak');
    }

    public function absenUser()
    {
        return $this->hasMany(AbsenUser::class, 'id_anak');
    }
    
    protected $appends = ['foto_url'];

    public function getFotoUrlAttribute()
    {
        if ($this->foto) {
            return url("storage/Anak/{$this->id_anak}/{$this->foto}");
        }
        
        return url('images/default.png');
    }
    
    public function getFotoFolderAttribute()
    {
        $folderPath = "Anak/{$this->id_anak}";

        if (Storage::exists($folderPath)) {
            return collect(Storage::files($folderPath))
                ->filter(function($file) {
                    return in_array(pathinfo($file, PATHINFO_EXTENSION), ['jpg', 'png', 'jpeg']);
                })
                ->map(function($file) {
                    return asset('storage/' . $file);
                });
        }

        return collect();
    }
}