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
        'biaya_pendidikan_perbulan',
        'bantuan_lembaga_formal_lain',
        'bantuan_lembaga_formal_lain_sebesar',
        'kondisi_penerima_manfaat',
        'tanggal_survey',
        'petugas_survey',
        'hasil_survey',
        'keterangan_hasil',
    ];

    /**
     * Relationship to Keluarga model.
     */
    public function keluarga()
    {
        return $this->belongsTo(Keluarga::class, 'id_keluarga');
    }
}
