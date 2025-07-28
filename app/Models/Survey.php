<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Survey extends Model
{
    use HasFactory;

    protected $table = 'survey';
    protected $primaryKey = 'id_survey';

    protected $fillable = [
        'id_keluarga',
        'pekerjaan_kepala_keluarga',
        'penghasilan',
        'pendidikan_kepala_keluarga',
        'jumlah_tanggungan',
        'kepemilikan_tabungan',
        'jumlah_makan',
        'kepemilikan_tanah',
        'kepemilikan_rumah',
        'kondisi_rumah_dinding',
        'kondisi_rumah_lantai',
        'kepemilikan_kendaraan',
        'kepemilikan_elektronik',
        'sumber_air_bersih',
        'jamban_limbah',
        'tempat_sampah',
        'perokok',
        'konsumen_miras',
        'persediaan_p3k',
        'makan_buah_sayur',
        'solat_lima_waktu',
        'membaca_alquran',
        'majelis_taklim',
        'membaca_koran',
        'pengurus_organisasi',
        'pengurus_organisasi_sebagai',
        'status_anak',
        'kepribadian_anak',
        'kondisi_fisik_anak',
        'keterangan_disabilitas',
        'biaya_pendidikan_perbulan',
        'bantuan_lembaga_formal_lain',
        'bantuan_lembaga_formal_lain_sebesar',
        'kondisi_penerima_manfaat',
        'tanggal_survey',
        'petugas_survey',
        'hasil_survey',
        'keterangan_hasil',
        'approved_by',
        'approved_at',
        'rejection_reason',
        'approval_notes'
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'tanggal_survey' => 'date'
    ];

    public function keluarga()
    {
        return $this->belongsTo(Keluarga::class, 'id_keluarga');
    }

    public function approvedBy()
    {
        return $this->belongsTo(AdminCabang::class, 'approved_by', 'id_admin_cabang');
    }

    public function scopePending($query)
    {
        return $query->where('hasil_survey', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('hasil_survey', 'layak');
    }

    public function scopeRejected($query)
    {
        return $query->where('hasil_survey', 'tidak layak');
    }

    public function scopeByKacab($query, $kacabId)
    {
        return $query->whereHas('keluarga', function ($q) use ($kacabId) {
            $q->where('id_kacab', $kacabId);
        });
    }
}
