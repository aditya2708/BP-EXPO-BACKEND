<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Raport extends Model
{
    use HasFactory;

    protected $table = 'raport';
    protected $primaryKey = 'id_raport';
    
    protected $fillable = [
        'id_anak',
        'id_semester',
        'total_kehadiran',
        'persentase_kehadiran',
        'ranking',
        'catatan_wali_kelas',
        'tanggal_terbit',
        'status'
    ];

    protected $casts = [
        'persentase_kehadiran' => 'decimal:2',
        'tanggal_terbit' => 'date'
    ];

    const STATUS_DRAFT = 'draft';
    const STATUS_PUBLISHED = 'published';
    const STATUS_ARCHIVED = 'archived';

    // Relations
    public function anak()
    {
        return $this->belongsTo(Anak::class, 'id_anak');
    }

    public function semester()
    {
        return $this->belongsTo(Semester::class, 'id_semester');
    }

    public function raportDetail()
    {
        return $this->hasMany(RaportDetail::class, 'id_raport');
    }

    // Scopes
    public function scopePublished($query)
    {
        return $query->where('status', self::STATUS_PUBLISHED);
    }

    public function scopeDraft($query)
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    // Methods
    public function publish()
    {
        $this->update([
            'status' => self::STATUS_PUBLISHED,
            'tanggal_terbit' => now()
        ]);
    }

    public function archive()
    {
        $this->update(['status' => self::STATUS_ARCHIVED]);
    }

    public function getNilaiRataRataAttribute()
    {
        return $this->raportDetail()->avg('nilai_akhir');
    }

    public function generateFromPenilaian()
    {
        $penilaianData = Penilaian::where('id_anak', $this->id_anak)
            ->where('id_semester', $this->id_semester)
            ->with(['materi', 'jenisPenilaian'])
            ->get()
            ->groupBy('id_materi');

        foreach ($penilaianData as $idMateri => $penilaianGroup) {
            $nilaiAkhir = 0;
            
            foreach ($penilaianGroup as $penilaian) {
                $bobot = $penilaian->jenisPenilaian->bobot_decimal;
                $nilaiAkhir += $penilaian->nilai * $bobot;
            }

            $materi = $penilaianGroup->first()->materi;
            
            $this->raportDetail()->updateOrCreate(
                ['mata_pelajaran' => $materi->mata_pelajaran ?? 'Unknown'],
                [
                    'nilai_akhir' => $nilaiAkhir,
                    'nilai_huruf' => $this->convertToHuruf($nilaiAkhir),
                    'kkm' => 70,
                    'keterangan' => $nilaiAkhir >= 70 ? 'Tuntas' : 'Belum Tuntas'
                ]
            );
        }
    }

    private function convertToHuruf($nilai)
    {
        if ($nilai >= 90) return 'A';
        if ($nilai >= 80) return 'B';
        if ($nilai >= 70) return 'C';
        if ($nilai >= 60) return 'D';
        return 'E';
    }
}
