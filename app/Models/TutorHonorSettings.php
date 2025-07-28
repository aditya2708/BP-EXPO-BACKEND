<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Helpers\CurrencyHelper;

class TutorHonorSettings extends Model
{
    use HasFactory;

    protected $table = 'tutor_honor_settings';
    protected $primaryKey = 'id_setting';
    
    protected $fillable = [
        'cpb_rate',
        'pb_rate', 
        'npb_rate',
        'payment_system',
        'flat_monthly_rate',
        'session_rate',
        'per_student_rate',
        'is_active',
        'created_by',
        'updated_by'
    ];

    protected $casts = [
        'cpb_rate' => 'decimal:2',
        'pb_rate' => 'decimal:2',
        'npb_rate' => 'decimal:2',
        'flat_monthly_rate' => 'decimal:2',
        'session_rate' => 'decimal:2',
        'per_student_rate' => 'decimal:2',
        'is_active' => 'boolean'
    ];

    /**
     * Get the user who created this setting
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by', 'id_users');
    }

    /**
     * Get the user who last updated this setting
     */
    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by', 'id_users');
    }

    /**
     * Scope to get only active settings
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get the current active setting
     */
    public static function getActiveSetting()
    {
        return static::active()->latest()->first();
    }

    /**
     * Set this setting as active and deactivate others
     */
    public function setAsActive()
    {
        // Deactivate all other settings
        static::where('id_setting', '!=', $this->id_setting)
              ->update(['is_active' => false]);
        
        // Activate this setting
        $this->update(['is_active' => true]);
        
        return $this;
    }

    /**
     * Check if field was changed
     */
    public function was($attribute)
    {
        return $this->getOriginal($attribute);
    }

    /**
     * Get formatted rates for display
     */
    public function getFormattedRatesAttribute()
    {
        $rates = [];
        
        switch ($this->payment_system) {
            case 'flat_monthly':
                $rates['flat_monthly'] = CurrencyHelper::formatRupiah($this->flat_monthly_rate);
                break;
                
            case 'per_session':
                $rates['session'] = CurrencyHelper::formatRupiah($this->session_rate);
                break;
                
            case 'per_student_category':
                $rates['cpb'] = CurrencyHelper::formatRupiah($this->cpb_rate);
                $rates['pb'] = CurrencyHelper::formatRupiah($this->pb_rate);
                $rates['npb'] = CurrencyHelper::formatRupiah($this->npb_rate);
                break;
                
            case 'session_per_student_category':
                $rates['session'] = CurrencyHelper::formatRupiah($this->session_rate);
                $rates['cpb'] = CurrencyHelper::formatRupiah($this->cpb_rate);
                $rates['pb'] = CurrencyHelper::formatRupiah($this->pb_rate);
                $rates['npb'] = CurrencyHelper::formatRupiah($this->npb_rate);
                break;
        }
        
        return $rates;
    }

    /**
     * Calculate honor for given parameters
     */
    public function calculateHonor($cpbCount = 0, $pbCount = 0, $npbCount = 0, $sessionCount = 0, $hourCount = 0)
    {
        // Handle array parameter for backward compatibility
        if (is_array($cpbCount)) {
            $params = $cpbCount;
            $cpbCount = $params['cpb_count'] ?? 0;
            $pbCount = $params['pb_count'] ?? 0;
            $npbCount = $params['npb_count'] ?? 0;
            $sessionCount = $params['session_count'] ?? 0;
            $hourCount = $params['hour_count'] ?? 0;
        }

        $totalStudents = $cpbCount + $pbCount + $npbCount;
        
        $cpbAmount = $cpbCount * ($this->cpb_rate ?? 0);
        $pbAmount = $pbCount * ($this->pb_rate ?? 0);
        $npbAmount = $npbCount * ($this->npb_rate ?? 0);
        
        $calculation = [
            'session_amount' => 0,
            'student_amount' => 0,
            'flat_amount' => 0,
            'total_amount' => 0,
            'cpb_amount' => $cpbAmount,
            'pb_amount' => $pbAmount,
            'npb_amount' => $npbAmount,
            'breakdown' => []
        ];
        
        switch ($this->payment_system) {
            case 'flat_monthly':
                $calculation['flat_amount'] = $this->flat_monthly_rate ?? 0;
                $calculation['total_amount'] = $this->flat_monthly_rate ?? 0;
                $calculation['breakdown']['flat_monthly'] = [
                    'rate' => $this->flat_monthly_rate ?? 0,
                    'amount' => $this->flat_monthly_rate ?? 0,
                    'formatted_rate' => CurrencyHelper::formatRupiah($this->flat_monthly_rate ?? 0),
                    'formatted_amount' => CurrencyHelper::formatRupiah($this->flat_monthly_rate ?? 0)
                ];
                break;
                
            case 'per_session':
                $calculation['session_amount'] = $sessionCount * ($this->session_rate ?? 0);
                $calculation['total_amount'] = $calculation['session_amount'];
                $calculation['breakdown']['session'] = [
                    'count' => $sessionCount,
                    'rate' => $this->session_rate ?? 0,
                    'amount' => $calculation['session_amount'],
                    'formatted_rate' => CurrencyHelper::formatRupiah($this->session_rate ?? 0),
                    'formatted_amount' => CurrencyHelper::formatRupiah($calculation['session_amount'])
                ];
                break;
                
            case 'per_student_category':
                $calculation['student_amount'] = $cpbAmount + $pbAmount + $npbAmount;
                $calculation['total_amount'] = $calculation['student_amount'];
                $calculation['breakdown'] = [
                    'cpb' => [
                        'count' => $cpbCount, 
                        'rate' => $this->cpb_rate ?? 0, 
                        'amount' => $cpbAmount,
                        'formatted_rate' => CurrencyHelper::formatRupiah($this->cpb_rate ?? 0),
                        'formatted_amount' => CurrencyHelper::formatRupiah($cpbAmount)
                    ],
                    'pb' => [
                        'count' => $pbCount, 
                        'rate' => $this->pb_rate ?? 0, 
                        'amount' => $pbAmount,
                        'formatted_rate' => CurrencyHelper::formatRupiah($this->pb_rate ?? 0),
                        'formatted_amount' => CurrencyHelper::formatRupiah($pbAmount)
                    ],
                    'npb' => [
                        'count' => $npbCount, 
                        'rate' => $this->npb_rate ?? 0, 
                        'amount' => $npbAmount,
                        'formatted_rate' => CurrencyHelper::formatRupiah($this->npb_rate ?? 0),
                        'formatted_amount' => CurrencyHelper::formatRupiah($npbAmount)
                    ]
                ];
                break;
                
            case 'session_per_student_category':
                $calculation['session_amount'] = $sessionCount * ($this->session_rate ?? 0);
                $calculation['student_amount'] = $cpbAmount + $pbAmount + $npbAmount;
                $calculation['total_amount'] = $calculation['session_amount'] + $calculation['student_amount'];
                $calculation['breakdown'] = [
                    'session' => [
                        'count' => $sessionCount, 
                        'rate' => $this->session_rate ?? 0, 
                        'amount' => $calculation['session_amount'],
                        'formatted_rate' => CurrencyHelper::formatRupiah($this->session_rate ?? 0),
                        'formatted_amount' => CurrencyHelper::formatRupiah($calculation['session_amount'])
                    ],
                    'cpb' => [
                        'count' => $cpbCount, 
                        'rate' => $this->cpb_rate ?? 0, 
                        'amount' => $cpbAmount,
                        'formatted_rate' => CurrencyHelper::formatRupiah($this->cpb_rate ?? 0),
                        'formatted_amount' => CurrencyHelper::formatRupiah($cpbAmount)
                    ],
                    'pb' => [
                        'count' => $pbCount, 
                        'rate' => $this->pb_rate ?? 0, 
                        'amount' => $pbAmount,
                        'formatted_rate' => CurrencyHelper::formatRupiah($this->pb_rate ?? 0),
                        'formatted_amount' => CurrencyHelper::formatRupiah($pbAmount)
                    ],
                    'npb' => [
                        'count' => $npbCount, 
                        'rate' => $this->npb_rate ?? 0, 
                        'amount' => $npbAmount,
                        'formatted_rate' => CurrencyHelper::formatRupiah($this->npb_rate ?? 0),
                        'formatted_amount' => CurrencyHelper::formatRupiah($npbAmount)
                    ]
                ];
                break;
        }
        
        // Add formatted total to calculation
        $calculation['formatted_total'] = CurrencyHelper::formatRupiah($calculation['total_amount']);
        
        return $calculation;
    }

    /**
     * Get payment system display name
     */
    public function getPaymentSystemNameAttribute()
    {
        $names = [
            'flat_monthly' => 'Honor Bulanan Tetap',
            'per_session' => 'Per Sesi/Pertemuan',
            'per_student_category' => 'Per Kategori Siswa',
            'session_per_student_category' => 'Per Sesi + Per Kategori Siswa'
        ];
        
        return $names[$this->payment_system] ?? $this->payment_system;
    }

    /**
     * Validation rules for honor settings
     */
    public static function validationRules()
    {
        return [
            'payment_system' => 'required|in:flat_monthly,per_session,per_student_category,session_per_student_category',
            'cpb_rate' => 'required_if:payment_system,per_student_category,session_per_student_category|nullable|numeric|min:0|max:999999.99',
            'pb_rate' => 'required_if:payment_system,per_student_category,session_per_student_category|nullable|numeric|min:0|max:999999.99',
            'npb_rate' => 'required_if:payment_system,per_student_category,session_per_student_category|nullable|numeric|min:0|max:999999.99',
            'flat_monthly_rate' => 'required_if:payment_system,flat_monthly|nullable|numeric|min:0|max:99999999.99',
            'session_rate' => 'required_if:payment_system,per_session,session_per_student_category|nullable|numeric|min:0|max:999999.99',
            'per_student_rate' => 'nullable|numeric|min:0|max:999999.99',
            'is_active' => 'nullable|boolean'
        ];
    }
}