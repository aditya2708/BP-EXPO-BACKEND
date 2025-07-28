<?php

namespace App\Services;

use App\Models\TutorHonor;
use App\Models\TutorHonorDetail;
use App\Models\TutorHonorSettings;
use App\Models\Aktivitas;
use App\Models\Absen;
use App\Models\AbsenUser;
use App\Models\Anak;
use App\Helpers\CurrencyHelper;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class TutorHonorService
{
    protected $honorPerSiswa = 10000; // Fallback rate

    public function calculateMonthlyHonor($tutorId, $month, $year)
    {
        DB::beginTransaction();
        
        try {
            // Get active honor settings
            $honorSettings = TutorHonorSettings::getActiveSetting();
            if (!$honorSettings) {
                throw new \Exception('No active honor settings found');
            }

            $tutorHonor = TutorHonor::firstOrCreate([
                'id_tutor' => $tutorId,
                'bulan' => $month,
                'tahun' => $year
            ]);

            // Store payment system used
            $tutorHonor->payment_system_used = $honorSettings->payment_system;
            $tutorHonor->save();

            $aktivitasList = $this->getTutorActivitiesInMonth($tutorId, $month, $year);
            
            $tutorHonor->details()->delete();
            
            // For flat_monthly, we only need one record
            if ($honorSettings->payment_system === 'flat_monthly') {
                $this->handleFlatMonthlyCalculation($tutorHonor, $aktivitasList, $honorSettings);
            } else {
                $this->handleActivityBasedCalculation($tutorHonor, $aktivitasList, $honorSettings);
            }

            $tutorHonor->calculateTotalHonor();
            
            DB::commit();
            return $tutorHonor->load('details.aktivitas');
            
        } catch (\Exception $e) {
            DB::rollback();
            throw $e;
        }
    }

    protected function handleFlatMonthlyCalculation($tutorHonor, $aktivitasList, $honorSettings)
    {
        $totalStudents = 0;
        
        // Still track individual activities for reporting
        foreach ($aktivitasList as $aktivitas) {
            $attendanceBreakdown = $this->getStudentAttendanceByStatus($aktivitas->id_aktivitas);
            $activityStudents = $attendanceBreakdown['cpb_count'] + 
                               $attendanceBreakdown['pb_count'] + 
                               $attendanceBreakdown['npb_count'];
            
            $totalStudents += $activityStudents;

            TutorHonorDetail::create([
                'id_honor' => $tutorHonor->id_honor,
                'id_aktivitas' => $aktivitas->id_aktivitas,
                'jumlah_siswa_hadir' => $activityStudents,
                'cpb_count' => $attendanceBreakdown['cpb_count'],
                'pb_count' => $attendanceBreakdown['pb_count'],
                'npb_count' => $attendanceBreakdown['npb_count'],
                'cpb_amount' => 0,
                'pb_amount' => 0,
                'npb_amount' => 0,
                'cpb_rate' => 0,
                'pb_rate' => 0,
                'npb_rate' => 0,
                'session_count' => 1,
                'hour_count' => 0,
                'session_amount' => 0,
                'hour_amount' => 0,
                'base_amount' => 0,
                'honor_per_aktivitas' => 0, // Individual activities get 0, total goes to first record
                'tanggal_aktivitas' => $aktivitas->tanggal
            ]);
        }

        // Create/update first record with total monthly amount
        if ($aktivitasList->count() > 0) {
            $firstDetail = $tutorHonor->details()->first();
            if ($firstDetail) {
                $firstDetail->honor_per_aktivitas = $honorSettings->flat_monthly_rate ?? 0;
                $firstDetail->save();
            }
        }
    }

    protected function handleActivityBasedCalculation($tutorHonor, $aktivitasList, $honorSettings)
    {
        foreach ($aktivitasList as $aktivitas) {
            $attendanceBreakdown = $this->getStudentAttendanceByStatus($aktivitas->id_aktivitas);
            
            $totalStudents = $attendanceBreakdown['cpb_count'] + 
                           $attendanceBreakdown['pb_count'] + 
                           $attendanceBreakdown['npb_count'];
            
            // Calculate individual amounts per category
            $cpbAmount = $attendanceBreakdown['cpb_count'] * ($honorSettings->cpb_rate ?? 0);
            $pbAmount = $attendanceBreakdown['pb_count'] * ($honorSettings->pb_rate ?? 0);
            $npbAmount = $attendanceBreakdown['npb_count'] * ($honorSettings->npb_rate ?? 0);
            
            // Calculate honor for this activity
            $calculation = $this->calculateHonorForActivity($honorSettings, $attendanceBreakdown, $aktivitas);

            TutorHonorDetail::create([
                'id_honor' => $tutorHonor->id_honor,
                'id_aktivitas' => $aktivitas->id_aktivitas,
                'jumlah_siswa_hadir' => $totalStudents,
                'cpb_count' => $attendanceBreakdown['cpb_count'],
                'pb_count' => $attendanceBreakdown['pb_count'],
                'npb_count' => $attendanceBreakdown['npb_count'],
                'cpb_amount' => $cpbAmount,
                'pb_amount' => $pbAmount,
                'npb_amount' => $npbAmount,
                'cpb_rate' => $honorSettings->cpb_rate ?? 0,
                'pb_rate' => $honorSettings->pb_rate ?? 0,
                'npb_rate' => $honorSettings->npb_rate ?? 0,
                'session_count' => $calculation['session_count'],
                'hour_count' => 0,
                'session_amount' => $calculation['session_amount'],
                'hour_amount' => 0,
                'base_amount' => 0,
                'honor_per_aktivitas' => $calculation['total_amount'],
                'tanggal_aktivitas' => $aktivitas->tanggal
            ]);
        }
    }

    /**
     * Calculate honor for a single activity based on payment system
     */
    protected function calculateHonorForActivity($honorSettings, $attendanceBreakdown, $aktivitas)
    {
        $totalStudents = $attendanceBreakdown['cpb_count'] + 
                        $attendanceBreakdown['pb_count'] + 
                        $attendanceBreakdown['npb_count'];

        // Prepare parameters based on payment system
        $params = [
            'cpb_count' => 0,
            'pb_count' => 0,
            'npb_count' => 0,
            'session_count' => 0
        ];

        $breakdown = [
            'session_count' => 0,
            'session_amount' => 0,
            'total_amount' => 0
        ];

        switch ($honorSettings->payment_system) {
            case 'per_session':
                $params['session_count'] = 1;
                $breakdown['session_count'] = 1;
                $breakdown['session_amount'] = $honorSettings->session_rate ?? 0;
                $breakdown['total_amount'] = $breakdown['session_amount'];
                break;

            case 'per_student_category':
                $params['cpb_count'] = $attendanceBreakdown['cpb_count'];
                $params['pb_count'] = $attendanceBreakdown['pb_count'];
                $params['npb_count'] = $attendanceBreakdown['npb_count'];
                $calculation = $honorSettings->calculateHonor($params);
                $breakdown['total_amount'] = $calculation['total_amount'];
                break;

            case 'session_per_student_category':
                $params['session_count'] = 1;
                $params['cpb_count'] = $attendanceBreakdown['cpb_count'];
                $params['pb_count'] = $attendanceBreakdown['pb_count'];
                $params['npb_count'] = $attendanceBreakdown['npb_count'];
                $breakdown['session_count'] = 1;
                $breakdown['session_amount'] = $honorSettings->session_rate ?? 0;
                
                // Calculate student category amounts
                $studentAmount = ($attendanceBreakdown['cpb_count'] * ($honorSettings->cpb_rate ?? 0)) +
                               ($attendanceBreakdown['pb_count'] * ($honorSettings->pb_rate ?? 0)) +
                               ($attendanceBreakdown['npb_count'] * ($honorSettings->npb_rate ?? 0));
                
                $breakdown['total_amount'] = $breakdown['session_amount'] + $studentAmount;
                break;

            case 'flat_monthly':
                // For flat monthly, individual activities don't get separate amounts
                $breakdown['session_count'] = 1;
                $breakdown['total_amount'] = 0; // Will be set at month level
                break;

            default:
                // Fallback to per_student_category
                $params['cpb_count'] = $attendanceBreakdown['cpb_count'];
                $params['pb_count'] = $attendanceBreakdown['pb_count'];
                $params['npb_count'] = $attendanceBreakdown['npb_count'];
                $calculation = $honorSettings->calculateHonor($params);
                $breakdown['total_amount'] = $calculation['total_amount'];
                break;
        }

        return $breakdown;
    }

    public function getHonorSummary($tutorId, $year = null)
    {
        $year = $year ?? Carbon::now()->year;
        
        $honors = TutorHonor::byTutor($tutorId)
            ->where('tahun', $year)
            ->orderBy('bulan')
            ->get();

        // Get current settings for context
        $currentSettings = TutorHonorSettings::getActiveSetting();

        return [
            'year' => $year,
            'total_honor_tahun' => $honors->sum('total_honor'),
            'total_aktivitas_tahun' => $honors->sum('total_aktivitas'),
            'rata_rata_bulanan' => $honors->avg('total_honor'),
            'honors_per_bulan' => $honors,
            'current_settings' => $currentSettings,
            'formatted_total_honor_tahun' => CurrencyHelper::formatRupiah($honors->sum('total_honor')),
            'formatted_rata_rata_bulanan' => CurrencyHelper::formatRupiah($honors->avg('total_honor'))
        ];
    }

    public function getTutorHonorDetail($tutorId, $month, $year)
    {
        $honor = TutorHonor::byTutor($tutorId)
            ->byMonth($month, $year)
            ->with(['details.aktivitas', 'tutor'])
            ->first();

        if ($honor) {
            // Get current settings for context
            $currentSettings = TutorHonorSettings::getActiveSetting();
            $honor->current_settings = $currentSettings;
        }

        return $honor;
    }

    public function approveHonor($honorId)
    {
        $honor = TutorHonor::findOrFail($honorId);
        $honor->update(['status' => 'approved']);
        return $honor;
    }

    public function markAsPaid($honorId)
    {
        $honor = TutorHonor::findOrFail($honorId);
        $honor->update(['status' => 'paid']);
        return $honor;
    }

    public function getMonthlyStats($tutorId, $month, $year)
    {
        $honor = $this->getTutorHonorDetail($tutorId, $month, $year);
        
        if (!$honor) {
            return [
                'total_honor' => 0,
                'total_aktivitas' => 0,
                'total_siswa' => 0,
                'rata_rata_siswa' => 0,
                'formatted_total_honor' => CurrencyHelper::formatRupiah(0)
            ];
        }

        return [
            'total_honor' => $honor->total_honor,
            'total_aktivitas' => $honor->total_aktivitas,
            'total_siswa' => $honor->total_siswa_hadir,
            'rata_rata_siswa' => $honor->total_aktivitas > 0 ? 
                round($honor->total_siswa_hadir / $honor->total_aktivitas, 1) : 0,
            'formatted_total_honor' => CurrencyHelper::formatRupiah($honor->total_honor)
        ];
    }

    public function getHonorByPeriod($tutorId, $filters = [])
    {
        $query = TutorHonor::byTutor($tutorId)
            ->with(['details.aktivitas', 'tutor'])
            ->orderBy('tahun', 'desc')
            ->orderBy('bulan', 'desc');

        // Apply date filters
        if (isset($filters['start_date'])) {
            $startDate = Carbon::parse($filters['start_date']);
            $query->where(function($q) use ($startDate) {
                $q->where('tahun', '>', $startDate->year)
                  ->orWhere(function($subQ) use ($startDate) {
                      $subQ->where('tahun', $startDate->year)
                           ->where('bulan', '>=', $startDate->month);
                  });
            });
        }

        if (isset($filters['end_date'])) {
            $endDate = Carbon::parse($filters['end_date']);
            $query->where(function($q) use ($endDate) {
                $q->where('tahun', '<', $endDate->year)
                  ->orWhere(function($subQ) use ($endDate) {
                      $subQ->where('tahun', $endDate->year)
                           ->where('bulan', '<=', $endDate->month);
                  });
            });
        }

        // Apply status filter
        if (isset($filters['status']) && $filters['status'] !== '') {
            $query->where('status', $filters['status']);
        }

        // Apply year filter
        if (isset($filters['year'])) {
            $query->where('tahun', $filters['year']);
        }

        // Apply amount filters
        if (isset($filters['min_amount'])) {
            $query->where('total_honor', '>=', $filters['min_amount']);
        }

        if (isset($filters['max_amount'])) {
            $query->where('total_honor', '<=', $filters['max_amount']);
        }

        // Apply activities filters
        if (isset($filters['min_activities'])) {
            $query->where('total_aktivitas', '>=', $filters['min_activities']);
        }

        if (isset($filters['max_activities'])) {
            $query->where('total_aktivitas', '<=', $filters['max_activities']);
        }

        // Apply sorting
        if (isset($filters['sort_by'])) {
            $sortOrder = $filters['sort_order'] ?? 'desc';
            
            switch ($filters['sort_by']) {
                case 'amount':
                    $query->orderBy('total_honor', $sortOrder);
                    break;
                case 'activities':
                    $query->orderBy('total_aktivitas', $sortOrder);
                    break;
                case 'status':
                    $query->orderBy('status', $sortOrder);
                    break;
                case 'date':
                default:
                    $query->orderBy('tahun', $sortOrder)->orderBy('bulan', $sortOrder);
                    break;
            }
        }

        // Pagination
        $perPage = $filters['per_page'] ?? 10;
        $page = $filters['page'] ?? 1;

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    public function generatePeriodStatistics($tutorId, $filters = [])
    {
        $query = TutorHonor::byTutor($tutorId);

        // Apply same filters as getHonorByPeriod
        if (isset($filters['start_date'])) {
            $startDate = Carbon::parse($filters['start_date']);
            $query->where(function($q) use ($startDate) {
                $q->where('tahun', '>', $startDate->year)
                  ->orWhere(function($subQ) use ($startDate) {
                      $subQ->where('tahun', $startDate->year)
                           ->where('bulan', '>=', $startDate->month);
                  });
            });
        }

        if (isset($filters['end_date'])) {
            $endDate = Carbon::parse($filters['end_date']);
            $query->where(function($q) use ($endDate) {
                $q->where('tahun', '<', $endDate->year)
                  ->orWhere(function($subQ) use ($endDate) {
                      $subQ->where('tahun', $endDate->year)
                           ->where('bulan', '<=', $endDate->month);
                  });
            });
        }

        if (isset($filters['status']) && $filters['status'] !== '') {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['year'])) {
            $query->where('tahun', $filters['year']);
        }

        $honors = $query->get();

        // Calculate statistics
        $totalHonors = $honors->count();
        $totalHonorAmount = $honors->sum('total_honor');
        $totalAktivitas = $honors->sum('total_aktivitas');
        $totalSiswaHadir = $honors->sum('total_siswa_hadir');
        
        $avgHonorPerMonth = $totalHonors > 0 ? $totalHonorAmount / $totalHonors : 0;
        $avgAktivitasPerMonth = $totalHonors > 0 ? $totalAktivitas / $totalHonors : 0;
        $avgSiswaPerAktivitas = $totalAktivitas > 0 ? $totalSiswaHadir / $totalAktivitas : 0;

        // Status breakdown
        $statusBreakdown = $honors->groupBy('status')->map(function ($group) {
            return [
                'count' => $group->count(),
                'total_honor' => $group->sum('total_honor'),
                'formatted_total_honor' => CurrencyHelper::formatRupiah($group->sum('total_honor'))
            ];
        });

        // Payment system breakdown
        $paymentSystemBreakdown = $honors->groupBy('payment_system_used')->map(function ($group) {
            return [
                'count' => $group->count(),
                'total_honor' => $group->sum('total_honor'),
                'avg_honor' => $group->avg('total_honor'),
                'formatted_total_honor' => CurrencyHelper::formatRupiah($group->sum('total_honor')),
                'formatted_avg_honor' => CurrencyHelper::formatRupiah($group->avg('total_honor'))
            ];
        });

        // Monthly breakdown for charts
        $monthlyData = $honors->groupBy(function ($item) {
            return $item->tahun . '-' . str_pad($item->bulan, 2, '0', STR_PAD_LEFT);
        })->map(function ($group, $key) {
            return [
                'period' => $key,
                'total_honor' => $group->sum('total_honor'),
                'total_aktivitas' => $group->sum('total_aktivitas'),
                'total_siswa_hadir' => $group->sum('total_siswa_hadir'),
                'count' => $group->count(),
                'payment_systems' => $group->pluck('payment_system_used')->unique()->values(),
                'formatted_total_honor' => CurrencyHelper::formatRupiah($group->sum('total_honor'))
            ];
        })->sortKeys();

        // Highest and lowest performing months
        $highestMonth = $honors->sortByDesc('total_honor')->first();
        $lowestMonth = $honors->sortBy('total_honor')->first();

        return [
            'summary' => [
                'total_periods' => $totalHonors,
                'total_honor_amount' => $totalHonorAmount,
                'total_aktivitas' => $totalAktivitas,
                'total_siswa_hadir' => $totalSiswaHadir,
                'avg_honor_per_month' => round($avgHonorPerMonth, 2),
                'avg_aktivitas_per_month' => round($avgAktivitasPerMonth, 1),
                'avg_siswa_per_aktivitas' => round($avgSiswaPerAktivitas, 1),
                'formatted_total_honor_amount' => CurrencyHelper::formatRupiah($totalHonorAmount),
                'formatted_avg_honor_per_month' => CurrencyHelper::formatRupiah($avgHonorPerMonth)
            ],
            'status_breakdown' => $statusBreakdown,
            'payment_system_breakdown' => $paymentSystemBreakdown,
            'monthly_data' => $monthlyData->values(),
            'performance' => [
                'highest_month' => $highestMonth ? [
                    'bulan' => $highestMonth->bulan,
                    'tahun' => $highestMonth->tahun,
                    'bulan_nama' => $highestMonth->bulan_nama,
                    'total_honor' => $highestMonth->total_honor,
                    'payment_system' => $highestMonth->payment_system_used,
                    'formatted_total_honor' => CurrencyHelper::formatRupiah($highestMonth->total_honor)
                ] : null,
                'lowest_month' => $lowestMonth ? [
                    'bulan' => $lowestMonth->bulan,
                    'tahun' => $lowestMonth->tahun,
                    'bulan_nama' => $lowestMonth->bulan_nama,
                    'total_honor' => $lowestMonth->total_honor,
                    'payment_system' => $lowestMonth->payment_system_used,
                    'formatted_total_honor' => CurrencyHelper::formatRupiah($lowestMonth->total_honor)
                ] : null
            ],
            'filters_applied' => $filters
        ];
    }

    public function getMonthlyActivityBreakdown($tutorId, $month, $year)
    {
        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = Carbon::create($year, $month, 1)->endOfMonth();

        $aktivitasList = Aktivitas::where('id_tutor', $tutorId)
            ->whereBetween('tanggal', [$startDate, $endDate])
            ->get();

        $totalBreakdown = [
            'cpb_count' => 0,
            'pb_count' => 0,
            'npb_count' => 0,
            'total_activities' => $aktivitasList->count(),
            'activities_with_attendance' => 0
        ];

        foreach ($aktivitasList as $aktivitas) {
            $breakdown = $this->getStudentAttendanceByStatus($aktivitas->id_aktivitas);
            
            if ($breakdown['cpb_count'] + $breakdown['pb_count'] + $breakdown['npb_count'] > 0) {
                $totalBreakdown['activities_with_attendance']++;
            }
            
            $totalBreakdown['cpb_count'] += $breakdown['cpb_count'];
            $totalBreakdown['pb_count'] += $breakdown['pb_count'];
            $totalBreakdown['npb_count'] += $breakdown['npb_count'];
        }

        return $totalBreakdown;
    }

    public function calculateHonorPreview($tutorId, $month, $year, $customCounts = [])
    {
        $settings = TutorHonorSettings::getActiveSetting();
        if (!$settings) {
            throw new \Exception('No active honor settings found');
        }

        // Get actual data for the month if available
        $actualData = $this->getMonthlyActivityBreakdown($tutorId, $month, $year);
        
        // Use custom counts if provided, otherwise use actual data
        $cpbCount = $customCounts['cpb_count'] ?? $actualData['cpb_count'];
        $pbCount = $customCounts['pb_count'] ?? $actualData['pb_count'];
        $npbCount = $customCounts['npb_count'] ?? $actualData['npb_count'];
        $sessionCount = $customCounts['session_count'] ?? $actualData['activities_with_attendance'];

        // Prepare parameters based on payment system
        $params = [
            'cpb_count' => $cpbCount,
            'pb_count' => $pbCount,
            'npb_count' => $npbCount,
            'session_count' => $sessionCount
        ];

        $calculation = $settings->calculateHonor($params);

        return [
            'calculation' => $calculation,
            'setting' => $settings,
            'actual_data' => $actualData,
            'used_counts' => $params,
            'period' => [
                'month' => $month,
                'year' => $year
            ],
            'formatted_total' => CurrencyHelper::formatRupiah($calculation['total_amount'])
        ];
    }

    protected function getTutorActivitiesInMonth($tutorId, $month, $year)
    {
        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = Carbon::create($year, $month, 1)->endOfMonth();

        return Aktivitas::where('id_tutor', $tutorId)
            ->whereBetween('tanggal', [$startDate, $endDate])
            ->whereHas('absen', function($query) use ($tutorId) {
                $query->whereIn('absen', ['Ya', 'Terlambat'])
                      ->whereHas('absenUser', function($subQuery) use ($tutorId) {
                          $subQuery->where('id_tutor', $tutorId);
                      });
            })
            ->get();
    }

    protected function countStudentAttendance($aktivitasId)
    {
        return Absen::where('id_aktivitas', $aktivitasId)
            ->whereIn('absen', ['Ya', 'Terlambat'])
            ->whereHas('absenUser', function($query) {
                $query->whereNotNull('id_anak');
            })
            ->count();
    }

    /**
     * Get student attendance breakdown by status (CPB, PB, NPB) for an activity
     */
    protected function getStudentAttendanceByStatus($aktivitasId)
    {
        $attendanceData = Absen::where('id_aktivitas', $aktivitasId)
            ->whereIn('absen', ['Ya', 'Terlambat'])
            ->with(['absenUser.anak' => function($query) {
                $query->select('id_anak', 'status_cpb');
            }])
            ->get();

        $breakdown = [
            'cpb_count' => 0,
            'pb_count' => 0,
            'npb_count' => 0
        ];

        foreach ($attendanceData as $attendance) {
            if ($attendance->absenUser && $attendance->absenUser->anak) {
                $statusCpb = $attendance->absenUser->anak->status_cpb;
                
                switch ($statusCpb) {
                    case 'CPB':
                        $breakdown['cpb_count']++;
                        break;
                    case 'PB':
                        $breakdown['pb_count']++;
                        break;
                    case 'NPB':
                        $breakdown['npb_count']++;
                        break;
                }
            }
        }

        return $breakdown;
    }

    public function getYearRange($tutorId)
    {
        $minYear = Aktivitas::where('id_tutor', $tutorId)
            ->selectRaw('MIN(YEAR(tanggal)) as min_year')
            ->value('min_year');

        $maxYear = Aktivitas::where('id_tutor', $tutorId)
            ->selectRaw('MAX(YEAR(tanggal)) as max_year')
            ->value('max_year');

        return [
            'minYear' => $minYear ?: Carbon::now()->year,
            'maxYear' => $maxYear ?: Carbon::now()->year
        ];
    }

    public function validateHonorCalculation($tutorId, $month, $year)
    {
        $errors = [];

        // Check if settings exist
        $settings = TutorHonorSettings::getActiveSetting();
        if (!$settings) {
            $errors[] = 'No active honor settings found';
        }

        // Check if future period
        $currentDate = Carbon::now();
        $targetDate = Carbon::create($year, $month, 1);
        
        if ($targetDate->isFuture()) {
            $errors[] = 'Cannot calculate honor for future periods';
        }

        // Check if activities exist
        $activitiesCount = $this->getTutorActivitiesInMonth($tutorId, $month, $year)->count();
        if ($activitiesCount === 0) {
            $errors[] = 'No activities found for the specified period';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'activities_count' => $activitiesCount ?? 0,
            'settings' => $settings
        ];
    }
}