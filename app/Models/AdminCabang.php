<?php
// 4. app/Models/AdminCabang.php - Add these relations to existing model

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdminCabang extends Model
{
    use HasFactory;

    protected $table = 'admin_cabang';
    protected $primaryKey = 'id_admin_cabang';

    protected $fillable = [
        'user_id',
        'id_kacab',
        'nama_lengkap',
        'alamat',
        'no_hp',
        'foto'
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id_users');
    }

    public function kacab()
    {
        return $this->belongsTo(Kacab::class, 'id_kacab');
    }

    public function approvedSurveys()
    {
        return $this->hasMany(Survey::class, 'approved_by', 'id_admin_cabang');
    }

    // ADD THESE NEW RELATIONS:

    /**
     * Relasi ke kurikulum
     */
    public function kurikulum()
    {
        return $this->hasMany(Kurikulum::class, 'id_kacab', 'id_kacab');
    }

    /**
     * Relasi ke mata pelajaran
     */
    public function mataPelajaran()
    {
        return $this->hasMany(MataPelajaran::class, 'id_kacab', 'id_kacab');
    }

    // ADD THESE NEW METHODS:

    /**
     * Get active kurikulum
     */
    public function getActiveKurikulum()
    {
        return $this->kurikulum()->where('status', 'aktif')->first();
    }

    /**
     * Get kurikulum statistics
     */
    public function getKurikulumStats()
    {
        return [
            'total_kurikulum' => $this->kurikulum()->count(),
            'active_kurikulum' => $this->kurikulum()->where('status', 'aktif')->count(),
            'draft_kurikulum' => $this->kurikulum()->where('status', 'draft')->count(),
            'total_mata_pelajaran' => $this->mataPelajaran()->count()
        ];
    }

    // EXISTING METHODS REMAIN THE SAME...
    public function getPendingSurveysAttribute()
    {
        return Survey::pending()
            ->byKacab($this->id_kacab)
            ->with(['keluarga.shelter.wilbin', 'keluarga.anak'])
            ->get();
    }

    public function surveys()
    {
        return Survey::byKacab($this->id_kacab);
    }
}