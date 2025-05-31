<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NilaiSikap extends Model
{
    use HasFactory;

    protected $table = 'nilai_sikap';
    protected $primaryKey = 'id_nilai_sikap';
    
    protected $fillable = [
        'id_anak',
        'id_semester',
        'kedisiplinan',
        'kerjasama',
        'tanggung_jawab',
        'sopan_santun',
        'catatan_sikap'
    ];

    protected $casts = [
        'kedisiplinan' => 'decimal:2',
        'kerjasama' => 'decimal:2',
        'tanggung_jawab' => 'decimal:2',
        'sopan_santun' => 'decimal:2'
    ];

    // Relations
    public function anak()
    {
        return $this->belongsTo(Anak::class, 'id_anak');
    }

    public function semester()
    {
        return $this->belongsTo(Semester::class, 'id_semester');
    }

    // Methods
    public function getRataRataAttribute()
    {
        return ($this->kedisiplinan + $this->kerjasama + $this->tanggung_jawab + $this->sopan_santun) / 4;
    }

    public function getPredikatAttribute()
    {
        $rata = $this->rata_rata;
        if ($rata >= 90) return 'Sangat Baik';
        if ($rata >= 80) return 'Baik';
        if ($rata >= 70) return 'Cukup';
        return 'Perlu Pembinaan';
    }

    public function getNilaiHurufAttribute()
    {
        $rata = $this->rata_rata;
        if ($rata >= 90) return 'A';
        if ($rata >= 80) return 'B';
        if ($rata >= 70) return 'C';
        return 'D';
    }
}