<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Keuangan extends Model
{
    use HasFactory;

    protected $table = 'keuangan'; // Nama tabel
    protected $primaryKey = 'id_keuangan'; // Primary key
    protected $fillable = [
        'id_anak', 
        'tingkat_sekolah', 
        'semester', 
        'bimbel', 
        'eskul_dan_keagamaan', 
        'laporan', 
        'uang_tunai', 
        'donasi', 
        'subsidi_infak'
    ];

    /**
     * Cast attributes to proper types
     */
    protected $casts = [
        'id_anak' => 'integer',
        'bimbel' => 'decimal:2',
        'eskul_dan_keagamaan' => 'decimal:2',
        'laporan' => 'decimal:2',
        'uang_tunai' => 'decimal:2',
        'donasi' => 'decimal:2',
        'subsidi_infak' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Accessor for total kebutuhan
     */
    public function getTotalKebutuhanAttribute()
    {
        return ($this->bimbel ?? 0) + 
               ($this->eskul_dan_keagamaan ?? 0) + 
               ($this->laporan ?? 0) + 
               ($this->uang_tunai ?? 0);
    }

    /**
     * Accessor for total bantuan
     */
    public function getTotalBantuanAttribute()
    {
        return ($this->donasi ?? 0) + ($this->subsidi_infak ?? 0);
    }

    /**
     * Accessor for sisa tagihan
     */
    public function getSisaTagihanAttribute()
    {
        return max(0, $this->total_kebutuhan - $this->total_bantuan);
    }

    /**
     * Accessor for status lunas
     */
    public function getIsLunasAttribute()
    {
        return $this->total_kebutuhan <= $this->total_bantuan;
    }

    /**
     * Relasi ke tabel Anak.
     */
    public function anak()
    {
        return $this->belongsTo(Anak::class, 'id_anak');
    }
}