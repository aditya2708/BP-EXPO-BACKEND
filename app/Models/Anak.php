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

    const STATUS_CPB_BCPB = 'BCPB';
    const STATUS_CPB_NPB = 'NPB';
    const STATUS_CPB_CPB = 'CPB';
    const STATUS_CPB_PB = 'PB';

    const STATUS_KELUARGA_DENGAN = 'dengan_keluarga';
    const STATUS_KELUARGA_TANPA = 'tanpa_keluarga';
    const STATUS_KELUARGA_PINDAH = 'pindah_keluarga';

    protected $attributes = [
        'status_validasi' => 'aktif',
        'status_cpb' => 'NPB',
        'status_keluarga' => 'dengan_keluarga',
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
        'status_keluarga',
        'keterangan_keluarga',
        'hafalan',
        'pelajaran_favorit',
        'hobi',
        'prestasi',
        'jarak_rumah',
        'transportasi',
        'foto',
        'status',
        'background_story',
        'educational_goals',
        'personality_traits',
        'special_needs',
        'marketplace_featured',
        'sponsorship_date',
    ];

    protected $casts = [
        'personality_traits' => 'array',
        'marketplace_featured' => 'boolean',
        'sponsorship_date' => 'datetime',
    ];

    public function scopeAktif($query)
    {
        return $query->whereIn('status_validasi', self::STATUS_AKTIF);
    }

    public function scopeTidakAktif($query)
    {
        return $query->whereIn('status_validasi', self::STATUS_TIDAK_AKTIF);
    }

    public function scopeAvailableForSponsorship($query)
    {
        return $query->where('status_cpb', self::STATUS_CPB_CPB)
                    ->whereNull('id_donatur')
                    ->whereIn('status_validasi', self::STATUS_AKTIF);
    }

    public function scopeByCpbStatus($query, $status)
    {
        return $query->where('status_cpb', $status);
    }

    public function scopeForCpbReport($query, $shelterId)
    {
        return $query->where('id_shelter', $shelterId)
                    ->whereIn('status_validasi', self::STATUS_AKTIF)
                    ->with(['kelompok', 'keluarga']);
    }

    public function scopeByKelas($query, $kelas)
    {
        return $query->whereHas('kelompok', function($q) use ($kelas) {
            $q->where('kelas', $kelas);
        });
    }

    public function scopeByStatusOrangTua($query, $status)
    {
        return $query->whereHas('keluarga', function($q) use ($status) {
            $q->where('status_ortu', $status);
        });
    }

    public function scopeFeaturedMarketplace($query)
    {
        return $query->where('marketplace_featured', true);
    }

    public function scopeByGender($query, $gender)
    {
        return $query->where('jenis_kelamin', $gender);
    }

    public function scopeByAgeRange($query, $minAge, $maxAge)
    {
        $currentDate = now();
        $maxBirthDate = $currentDate->copy()->subYears($minAge);
        $minBirthDate = $currentDate->copy()->subYears($maxAge + 1);
        
        return $query->whereBetween('tanggal_lahir', [$minBirthDate, $maxBirthDate]);
    }

    public function scopeByHafalan($query, $hafalan)
    {
        return $query->where('hafalan', $hafalan);
    }

    public function scopeByRegion($query, $shelterId)
    {
        return $query->where('id_shelter', $shelterId);
    }

    public function getUmurAttribute()
    {
        if (!$this->tanggal_lahir) return null;
        return now()->diffInYears($this->tanggal_lahir);
    }

    public function getIsAvailableForSponsorshipAttribute()
    {
        return $this->status_cpb === self::STATUS_CPB_CPB && 
               is_null($this->id_donatur) && 
               in_array($this->status_validasi, self::STATUS_AKTIF);
    }

    public function keluarga()
    {
        return $this->belongsTo(Keluarga::class, 'id_keluarga', 'id_keluarga');
    }

    public function keluargaAktif()
    {
        return $this->belongsTo(Keluarga::class, 'id_keluarga', 'id_keluarga')->whereNull('deleted_at');
    }

    public function scopeWithActiveFamily($query)
    {
        return $query->whereHas('keluarga', function($q) {
            $q->whereNull('deleted_at');
        });
    }

    public function scopeByFamilyStatus($query, $status)
    {
        return $query->where('status_keluarga', $status);
    }

    public function scopeTanpaKeluarga($query)
    {
        return $query->where('status_keluarga', self::STATUS_KELUARGA_TANPA);
    }

    public function scopeDenganKeluarga($query)
    {
        return $query->where('status_keluarga', self::STATUS_KELUARGA_DENGAN);
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

    public function prestasi()
    {
        return $this->hasMany(Prestasi::class, 'id_anak');
    }
    
    protected $appends = ['foto_url', 'umur'];

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