<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Helpers\CurrencyHelper;

class TutorHonorDetail extends Model
{
    use HasFactory;

    protected $table = 'tutor_honor_detail';
    protected $primaryKey = 'id_detail';
    
    protected $fillable = [
        'id_honor',
        'id_aktivitas',
        'jumlah_siswa_hadir',
        'cpb_count',
        'pb_count', 
        'npb_count',
        'cpb_amount',
        'pb_amount',
        'npb_amount',
        'cpb_rate',
        'pb_rate',
        'npb_rate',
        'session_count',
        'session_amount',
        'hour_count',
        'hour_amount',
        'base_amount',
        'honor_per_aktivitas',
        'tanggal_aktivitas'
    ];

    protected $casts = [
        'honor_per_aktivitas' => 'decimal:2',
        'cpb_amount' => 'decimal:2',
        'pb_amount' => 'decimal:2', 
        'npb_amount' => 'decimal:2',
        'cpb_rate' => 'decimal:2',
        'pb_rate' => 'decimal:2',
        'npb_rate' => 'decimal:2',
        'session_amount' => 'decimal:2',
        'hour_amount' => 'decimal:2',
        'base_amount' => 'decimal:2',
        'tanggal_aktivitas' => 'date'
    ];

    public function tutorHonor()
    {
        return $this->belongsTo(TutorHonor::class, 'id_honor', 'id_honor');
    }

    public function aktivitas()
    {
        return $this->belongsTo(Aktivitas::class, 'id_aktivitas', 'id_aktivitas');
    }

    /**
     * Get formatted honor breakdown for display
     */
    public function getHonorBreakdownAttribute()
    {
        $paymentSystem = $this->tutorHonor?->payment_system_used;
        
        $breakdown = [
            'cpb' => [
                'count' => $this->cpb_count,
                'rate' => $this->cpb_rate,
                'amount' => $this->cpb_amount,
                'formatted_amount' => CurrencyHelper::formatRupiah($this->cpb_amount)
            ],
            'pb' => [
                'count' => $this->pb_count,
                'rate' => $this->pb_rate,
                'amount' => $this->pb_amount,
                'formatted_amount' => CurrencyHelper::formatRupiah($this->pb_amount)
            ],
            'npb' => [
                'count' => $this->npb_count,
                'rate' => $this->npb_rate,
                'amount' => $this->npb_amount,
                'formatted_amount' => CurrencyHelper::formatRupiah($this->npb_amount)
            ],
            'session' => [
                'count' => $this->session_count,
                'amount' => $this->session_amount,
                'formatted_amount' => CurrencyHelper::formatRupiah($this->session_amount)
            ],
            'hour' => [
                'count' => $this->hour_count,
                'amount' => $this->hour_amount,
                'formatted_amount' => CurrencyHelper::formatRupiah($this->hour_amount)
            ],
            'base' => [
                'amount' => $this->base_amount,
                'formatted_amount' => CurrencyHelper::formatRupiah($this->base_amount)
            ],
            'total' => [
                'count' => $this->jumlah_siswa_hadir,
                'amount' => $this->honor_per_aktivitas,
                'formatted_amount' => CurrencyHelper::formatRupiah($this->honor_per_aktivitas)
            ]
        ];

        // Add payment system specific breakdown
        switch ($paymentSystem) {
            case 'session_per_student':
                $breakdown['components'] = [
                    'session_component' => $this->session_amount,
                    'student_component' => $this->cpb_amount + $this->pb_amount + $this->npb_amount,
                    'formatted_session_component' => CurrencyHelper::formatRupiah($this->session_amount),
                    'formatted_student_component' => CurrencyHelper::formatRupiah($this->cpb_amount + $this->pb_amount + $this->npb_amount)
                ];
                break;

            case 'base_per_session':
                $breakdown['components'] = [
                    'base_component' => $this->base_amount,
                    'session_component' => $this->session_amount,
                    'formatted_base_component' => CurrencyHelper::formatRupiah($this->base_amount),
                    'formatted_session_component' => CurrencyHelper::formatRupiah($this->session_amount)
                ];
                break;

            case 'base_per_student':
                $breakdown['components'] = [
                    'base_component' => $this->base_amount,
                    'student_component' => $this->cpb_amount + $this->pb_amount + $this->npb_amount,
                    'formatted_base_component' => CurrencyHelper::formatRupiah($this->base_amount),
                    'formatted_student_component' => CurrencyHelper::formatRupiah($this->cpb_amount + $this->pb_amount + $this->npb_amount)
                ];
                break;

            case 'base_per_hour':
                $breakdown['components'] = [
                    'base_component' => $this->base_amount,
                    'hour_component' => $this->hour_amount,
                    'formatted_base_component' => CurrencyHelper::formatRupiah($this->base_amount),
                    'formatted_hour_component' => CurrencyHelper::formatRupiah($this->hour_amount)
                ];
                break;
        }

        return $breakdown;
    }

    /**
     * Check if this detail has status breakdown
     */
    public function hasStatusBreakdownAttribute()
    {
        return $this->cpb_count > 0 || $this->pb_count > 0 || $this->npb_count > 0;
    }

    /**
     * Check if this detail has session breakdown
     */
    public function hasSessionBreakdownAttribute()
    {
        return $this->session_count > 0 && $this->session_amount > 0;
    }

    /**
     * Check if this detail has hour breakdown
     */
    public function hasHourBreakdownAttribute()
    {
        return $this->hour_count > 0 && $this->hour_amount > 0;
    }

    /**
     * Check if this detail has base amount
     */
    public function hasBaseAmountAttribute()
    {
        return $this->base_amount > 0;
    }

    /**
     * Get percentage breakdown by status
     */
    public function getStatusPercentageAttribute()
    {
        if ($this->jumlah_siswa_hadir == 0) {
            return ['cpb' => 0, 'pb' => 0, 'npb' => 0];
        }

        return [
            'cpb' => round(($this->cpb_count / $this->jumlah_siswa_hadir) * 100, 1),
            'pb' => round(($this->pb_count / $this->jumlah_siswa_hadir) * 100, 1),
            'npb' => round(($this->npb_count / $this->jumlah_siswa_hadir) * 100, 1)
        ];
    }

    /**
     * Get dynamic breakdown based on payment system
     */
    public function getDynamicBreakdownAttribute()
    {
        $paymentSystem = $this->tutorHonor?->payment_system_used;
        
        switch ($paymentSystem) {
            case 'flat_monthly':
                return [
                    'type' => 'flat_monthly',
                    'monthly_rate' => $this->honor_per_aktivitas,
                    'session_count' => 1,
                    'total_amount' => $this->honor_per_aktivitas,
                    'formatted_monthly_rate' => CurrencyHelper::formatRupiah($this->honor_per_aktivitas),
                    'formatted_total_amount' => CurrencyHelper::formatRupiah($this->honor_per_aktivitas)
                ];

            case 'per_session':
                return [
                    'type' => 'per_session',
                    'session_count' => $this->session_count ?? 1,
                    'session_amount' => $this->session_amount,
                    'total_amount' => $this->honor_per_aktivitas,
                    'formatted_session_amount' => CurrencyHelper::formatRupiah($this->session_amount),
                    'formatted_total_amount' => CurrencyHelper::formatRupiah($this->honor_per_aktivitas)
                ];

            case 'per_student_category':
                return [
                    'type' => 'per_student_category',
                    'cpb' => [
                        'count' => $this->cpb_count, 
                        'rate' => $this->cpb_rate, 
                        'amount' => $this->cpb_amount,
                        'formatted_rate' => CurrencyHelper::formatRupiah($this->cpb_rate),
                        'formatted_amount' => CurrencyHelper::formatRupiah($this->cpb_amount)
                    ],
                    'pb' => [
                        'count' => $this->pb_count, 
                        'rate' => $this->pb_rate, 
                        'amount' => $this->pb_amount,
                        'formatted_rate' => CurrencyHelper::formatRupiah($this->pb_rate),
                        'formatted_amount' => CurrencyHelper::formatRupiah($this->pb_amount)
                    ],
                    'npb' => [
                        'count' => $this->npb_count, 
                        'rate' => $this->npb_rate, 
                        'amount' => $this->npb_amount,
                        'formatted_rate' => CurrencyHelper::formatRupiah($this->npb_rate),
                        'formatted_amount' => CurrencyHelper::formatRupiah($this->npb_amount)
                    ],
                    'total_amount' => $this->honor_per_aktivitas,
                    'formatted_total_amount' => CurrencyHelper::formatRupiah($this->honor_per_aktivitas)
                ];

            case 'per_hour':
                return [
                    'type' => 'per_hour',
                    'hour_count' => $this->hour_count ?? 2,
                    'hour_rate' => $this->hour_count > 0 ? $this->hour_amount / $this->hour_count : 0,
                    'hour_amount' => $this->hour_amount,
                    'total_amount' => $this->honor_per_aktivitas,
                    'formatted_hour_rate' => CurrencyHelper::formatRupiah($this->hour_count > 0 ? $this->hour_amount / $this->hour_count : 0),
                    'formatted_hour_amount' => CurrencyHelper::formatRupiah($this->hour_amount),
                    'formatted_total_amount' => CurrencyHelper::formatRupiah($this->honor_per_aktivitas)
                ];

            case 'base_per_session':
                return [
                    'type' => 'base_per_session',
                    'base_amount' => $this->base_amount,
                    'session_count' => $this->session_count ?? 1,
                    'session_amount' => $this->session_amount,
                    'total_amount' => $this->honor_per_aktivitas,
                    'formatted_base_amount' => CurrencyHelper::formatRupiah($this->base_amount),
                    'formatted_session_amount' => CurrencyHelper::formatRupiah($this->session_amount),
                    'formatted_total_amount' => CurrencyHelper::formatRupiah($this->honor_per_aktivitas)
                ];

            case 'base_per_student':
                return [
                    'type' => 'base_per_student',
                    'base_amount' => $this->base_amount,
                    'cpb' => [
                        'count' => $this->cpb_count, 
                        'rate' => $this->cpb_rate, 
                        'amount' => $this->cpb_amount,
                        'formatted_rate' => CurrencyHelper::formatRupiah($this->cpb_rate),
                        'formatted_amount' => CurrencyHelper::formatRupiah($this->cpb_amount)
                    ],
                    'pb' => [
                        'count' => $this->pb_count, 
                        'rate' => $this->pb_rate, 
                        'amount' => $this->pb_amount,
                        'formatted_rate' => CurrencyHelper::formatRupiah($this->pb_rate),
                        'formatted_amount' => CurrencyHelper::formatRupiah($this->pb_amount)
                    ],
                    'npb' => [
                        'count' => $this->npb_count, 
                        'rate' => $this->npb_rate, 
                        'amount' => $this->npb_amount,
                        'formatted_rate' => CurrencyHelper::formatRupiah($this->npb_rate),
                        'formatted_amount' => CurrencyHelper::formatRupiah($this->npb_amount)
                    ],
                    'student_component' => $this->cpb_amount + $this->pb_amount + $this->npb_amount,
                    'total_amount' => $this->honor_per_aktivitas,
                    'formatted_base_amount' => CurrencyHelper::formatRupiah($this->base_amount),
                    'formatted_student_component' => CurrencyHelper::formatRupiah($this->cpb_amount + $this->pb_amount + $this->npb_amount),
                    'formatted_total_amount' => CurrencyHelper::formatRupiah($this->honor_per_aktivitas)
                ];

            case 'base_per_hour':
                return [
                    'type' => 'base_per_hour',
                    'base_amount' => $this->base_amount,
                    'hour_count' => $this->hour_count ?? 2,
                    'hour_rate' => $this->hour_count > 0 ? $this->hour_amount / $this->hour_count : 0,
                    'hour_amount' => $this->hour_amount,
                    'total_amount' => $this->honor_per_aktivitas,
                    'formatted_base_amount' => CurrencyHelper::formatRupiah($this->base_amount),
                    'formatted_hour_rate' => CurrencyHelper::formatRupiah($this->hour_count > 0 ? $this->hour_amount / $this->hour_count : 0),
                    'formatted_hour_amount' => CurrencyHelper::formatRupiah($this->hour_amount),
                    'formatted_total_amount' => CurrencyHelper::formatRupiah($this->honor_per_aktivitas)
                ];

            case 'session_per_student':
                return [
                    'type' => 'session_per_student',
                    'session' => [
                        'count' => $this->session_count ?? 1, 
                        'amount' => $this->session_amount,
                        'formatted_amount' => CurrencyHelper::formatRupiah($this->session_amount)
                    ],
                    'cpb' => [
                        'count' => $this->cpb_count, 
                        'rate' => $this->cpb_rate, 
                        'amount' => $this->cpb_amount,
                        'formatted_rate' => CurrencyHelper::formatRupiah($this->cpb_rate),
                        'formatted_amount' => CurrencyHelper::formatRupiah($this->cpb_amount)
                    ],
                    'pb' => [
                        'count' => $this->pb_count, 
                        'rate' => $this->pb_rate, 
                        'amount' => $this->pb_amount,
                        'formatted_rate' => CurrencyHelper::formatRupiah($this->pb_rate),
                        'formatted_amount' => CurrencyHelper::formatRupiah($this->pb_amount)
                    ],
                    'npb' => [
                        'count' => $this->npb_count, 
                        'rate' => $this->npb_rate, 
                        'amount' => $this->npb_amount,
                        'formatted_rate' => CurrencyHelper::formatRupiah($this->npb_rate),
                        'formatted_amount' => CurrencyHelper::formatRupiah($this->npb_amount)
                    ],
                    'session_component' => $this->session_amount,
                    'student_component' => $this->cpb_amount + $this->pb_amount + $this->npb_amount,
                    'total_amount' => $this->honor_per_aktivitas,
                    'formatted_session_component' => CurrencyHelper::formatRupiah($this->session_amount),
                    'formatted_student_component' => CurrencyHelper::formatRupiah($this->cpb_amount + $this->pb_amount + $this->npb_amount),
                    'formatted_total_amount' => CurrencyHelper::formatRupiah($this->honor_per_aktivitas)
                ];

            default:
                return [
                    'type' => 'unknown',
                    'total_amount' => $this->honor_per_aktivitas,
                    'formatted_total_amount' => CurrencyHelper::formatRupiah($this->honor_per_aktivitas)
                ];
        }
    }

    /**
     * Get formatted total honor
     */
    public function getFormattedHonorAttribute()
    {
        return CurrencyHelper::formatRupiah($this->honor_per_aktivitas);
    }
}