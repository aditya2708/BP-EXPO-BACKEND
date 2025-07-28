<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Helpers\CurrencyHelper;

class TutorHonor extends Model
{
    use HasFactory;

    protected $table = 'tutor_honor';
    protected $primaryKey = 'id_honor';
    
    protected $fillable = [
        'id_tutor',
        'bulan',
        'tahun',
        'total_aktivitas',
        'total_siswa_hadir',
        'total_honor',
        'status',
        'payment_system_used'
    ];

    protected $casts = [
        'total_honor' => 'decimal:2',
        'honor_per_aktivitas' => 'decimal:2'
    ];

    public function tutor()
    {
        return $this->belongsTo(Tutor::class, 'id_tutor', 'id_tutor');
    }

    public function details()
    {
        return $this->hasMany(TutorHonorDetail::class, 'id_honor', 'id_honor');
    }

    public function scopeByMonth($query, $month, $year)
    {
        return $query->where('bulan', $month)->where('tahun', $year);
    }

    public function scopeByTutor($query, $tutorId)
    {
        return $query->where('id_tutor', $tutorId);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function calculateTotalHonor()
    {
        // For flat_monthly system, calculate differently
        if ($this->payment_system_used === 'flat_monthly') {
            $this->total_honor = $this->details()->first()?->honor_per_aktivitas ?? 0;
            $this->total_aktivitas = $this->details()->count();
            $this->total_siswa_hadir = $this->details()->sum('jumlah_siswa_hadir');
        } else {
            $this->total_honor = $this->details()->sum('honor_per_aktivitas');
            $this->total_aktivitas = $this->details()->count();
            $this->total_siswa_hadir = $this->details()->sum('jumlah_siswa_hadir');
        }
        
        $this->save();
        
        return $this->total_honor;
    }

    public function getFormattedHonorAttribute()
    {
        return CurrencyHelper::formatRupiah($this->total_honor);
    }

    public function getBulanNamaAttribute()
    {
        $bulanNama = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
        ];
        
        return $bulanNama[$this->bulan] ?? '';
    }

    public function getPaymentSystemDisplayAttribute()
    {
        $systems = [
            'flat_monthly' => 'Honor Bulanan Tetap',
            'per_session' => 'Per Sesi/Pertemuan',
            'per_student_category' => 'Per Kategori Siswa',
            'per_hour' => 'Per Jam',
            'base_per_session' => 'Dasar + Per Sesi',
            'base_per_student' => 'Dasar + Per Siswa',
            'base_per_hour' => 'Dasar + Per Jam',
            'session_per_student' => 'Per Sesi + Per Siswa'
        ];
        
        return $systems[$this->payment_system_used] ?? $this->payment_system_used ?? 'Unknown';
    }

    public function getDynamicSummaryAttribute()
    {
        $summary = [
            'payment_system' => $this->payment_system_used,
            'payment_system_display' => $this->payment_system_display,
            'total_honor' => $this->total_honor,
            'total_aktivitas' => $this->total_aktivitas,
            'total_siswa_hadir' => $this->total_siswa_hadir,
            'formatted_total_honor' => CurrencyHelper::formatRupiah($this->total_honor)
        ];

        // Add system-specific summaries with proper formatting
        switch ($this->payment_system_used) {
            case 'per_student_category':
            case 'base_per_student':
            case 'session_per_student':
                $summary['breakdown'] = [
                    'cpb_total' => $this->details->sum('cpb_amount'),
                    'pb_total' => $this->details->sum('pb_amount'),
                    'npb_total' => $this->details->sum('npb_amount'),
                    'cpb_count' => $this->details->sum('cpb_count'),
                    'pb_count' => $this->details->sum('pb_count'),
                    'npb_count' => $this->details->sum('npb_count'),
                    'formatted_cpb_total' => CurrencyHelper::formatRupiah($this->details->sum('cpb_amount')),
                    'formatted_pb_total' => CurrencyHelper::formatRupiah($this->details->sum('pb_amount')),
                    'formatted_npb_total' => CurrencyHelper::formatRupiah($this->details->sum('npb_amount'))
                ];
                break;

            case 'per_session':
            case 'base_per_session':
                $summary['breakdown'] = [
                    'total_sessions' => $this->details->sum('session_count'),
                    'session_total' => $this->details->sum('session_amount'),
                    'formatted_session_total' => CurrencyHelper::formatRupiah($this->details->sum('session_amount'))
                ];
                break;

            case 'per_hour':
            case 'base_per_hour':
                $summary['breakdown'] = [
                    'total_hours' => $this->details->sum('hour_count'),
                    'hour_total' => $this->details->sum('hour_amount'),
                    'formatted_hour_total' => CurrencyHelper::formatRupiah($this->details->sum('hour_amount'))
                ];
                break;

            case 'flat_monthly':
                $summary['breakdown'] = [
                    'monthly_rate' => $this->details->first()?->honor_per_aktivitas ?? 0,
                    'activities_tracked' => $this->total_aktivitas,
                    'formatted_monthly_rate' => CurrencyHelper::formatRupiah($this->details->first()?->honor_per_aktivitas ?? 0)
                ];
                break;

            default:
                $summary['breakdown'] = [
                    'total_amount' => $this->total_honor,
                    'formatted_total_amount' => CurrencyHelper::formatRupiah($this->total_honor)
                ];
                break;
        }

        return $summary;
    }

    public function hasStudentCategoryBreakdown()
    {
        return in_array($this->payment_system_used, [
            'per_student_category',
            'base_per_student',
            'session_per_student'
        ]);
    }

    public function hasSessionBreakdown()
    {
        return in_array($this->payment_system_used, [
            'per_session',
            'base_per_session',
            'session_per_student'
        ]);
    }

    public function hasHourBreakdown()
    {
        return in_array($this->payment_system_used, [
            'per_hour',
            'base_per_hour'
        ]);
    }

    public function hasBaseAmount()
    {
        return in_array($this->payment_system_used, [
            'base_per_session',
            'base_per_student',
            'base_per_hour'
        ]);
    }

    public function isFlatMonthly()
    {
        return $this->payment_system_used === 'flat_monthly';
    }

    /**
     * Get formatted breakdown by payment system
     */
    public function getFormattedBreakdownAttribute()
    {
        $breakdown = $this->dynamic_summary['breakdown'] ?? [];
        
        switch ($this->payment_system_used) {
            case 'per_student_category':
            case 'base_per_student':
            case 'session_per_student':
                return [
                    'cpb' => [
                        'count' => $breakdown['cpb_count'] ?? 0,
                        'total' => $breakdown['cpb_total'] ?? 0,
                        'formatted_total' => $breakdown['formatted_cpb_total'] ?? CurrencyHelper::formatRupiah(0)
                    ],
                    'pb' => [
                        'count' => $breakdown['pb_count'] ?? 0,
                        'total' => $breakdown['pb_total'] ?? 0,
                        'formatted_total' => $breakdown['formatted_pb_total'] ?? CurrencyHelper::formatRupiah(0)
                    ],
                    'npb' => [
                        'count' => $breakdown['npb_count'] ?? 0,
                        'total' => $breakdown['npb_total'] ?? 0,
                        'formatted_total' => $breakdown['formatted_npb_total'] ?? CurrencyHelper::formatRupiah(0)
                    ]
                ];

            case 'per_session':
            case 'base_per_session':
                return [
                    'sessions' => [
                        'count' => $breakdown['total_sessions'] ?? 0,
                        'total' => $breakdown['session_total'] ?? 0,
                        'formatted_total' => $breakdown['formatted_session_total'] ?? CurrencyHelper::formatRupiah(0)
                    ]
                ];

            case 'per_hour':
            case 'base_per_hour':
                return [
                    'hours' => [
                        'count' => $breakdown['total_hours'] ?? 0,
                        'total' => $breakdown['hour_total'] ?? 0,
                        'formatted_total' => $breakdown['formatted_hour_total'] ?? CurrencyHelper::formatRupiah(0)
                    ]
                ];

            case 'flat_monthly':
                return [
                    'monthly' => [
                        'rate' => $breakdown['monthly_rate'] ?? 0,
                        'activities' => $breakdown['activities_tracked'] ?? 0,
                        'formatted_rate' => $breakdown['formatted_monthly_rate'] ?? CurrencyHelper::formatRupiah(0)
                    ]
                ];

            default:
                return [
                    'total' => [
                        'amount' => $this->total_honor,
                        'formatted_amount' => CurrencyHelper::formatRupiah($this->total_honor)
                    ]
                ];
        }
    }
}