<?php

namespace App\Http\Controllers\API\AdminShelter;

use App\Http\Controllers\Controller;
use App\Services\TutorHonorService;
use App\Models\TutorHonor;
use App\Models\TutorHonorSettings;
use App\Models\Tutor;
use App\Helpers\CurrencyHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Carbon\Carbon;

class TutorHonorController extends Controller
{
    protected $tutorHonorService;

    public function __construct(TutorHonorService $tutorHonorService)
    {
        $this->tutorHonorService = $tutorHonorService;
    }

    public function getTutorHonor($id_tutor, Request $request)
    {
        $user = Auth::user();
        
        if (!$user->adminShelter) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        $request->validate([
            'year' => 'nullable|integer|min:2020|max:' . (date('Y') + 1),
            'month' => 'nullable|integer|min:1|max:12',
            'status' => ['nullable', Rule::in(['draft', 'approved', 'paid'])],
            'per_page' => 'nullable|integer|min:1|max:100',
            'page' => 'nullable|integer|min:1'
        ]);

        $tutor = Tutor::where('id_shelter', $user->adminShelter->shelter->id_shelter)
                     ->findOrFail($id_tutor);

        try {
            $year = $request->input('year', Carbon::now()->year);
            $summary = $this->tutorHonorService->getHonorSummary($id_tutor, $year);

            // Ensure honors_per_bulan has formatted amounts
            if (isset($summary['honors_per_bulan'])) {
                $summary['honors_per_bulan'] = $summary['honors_per_bulan']->map(function ($honor) {
                    $honor->formatted_total_honor = CurrencyHelper::formatRupiah($honor->total_honor);
                    return $honor;
                });
            }

            return response()->json([
                'success' => true,
                'data' => $summary,
                'tutor' => $tutor
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch tutor honor: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getMonthlyDetail($id_tutor, $month, $year)
    {
        $user = Auth::user();
        
        if (!$user->adminShelter) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        if (!is_numeric($month) || $month < 1 || $month > 12) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid month parameter'
            ], 422);
        }

        if (!is_numeric($year) || $year < 2020 || $year > date('Y') + 1) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid year parameter'
            ], 422);
        }

        $tutor = Tutor::where('id_shelter', $user->adminShelter->shelter->id_shelter)
                     ->findOrFail($id_tutor);

        try {
            $honorDetail = $this->tutorHonorService->getTutorHonorDetail($id_tutor, $month, $year);
            $stats = $this->tutorHonorService->getMonthlyStats($id_tutor, $month, $year);

            // Ensure details have proper breakdown data structure and formatting
            if ($honorDetail && $honorDetail->details) {
                $honorDetail->details->transform(function ($detail) {
                    // Ensure all breakdown fields are present and numeric
                    $detail->cpb_count = (int) ($detail->cpb_count ?? 0);
                    $detail->pb_count = (int) ($detail->pb_count ?? 0);
                    $detail->npb_count = (int) ($detail->npb_count ?? 0);
                    $detail->cpb_amount = (float) ($detail->cpb_amount ?? 0);
                    $detail->pb_amount = (float) ($detail->pb_amount ?? 0);
                    $detail->npb_amount = (float) ($detail->npb_amount ?? 0);
                    $detail->cpb_rate = (float) ($detail->cpb_rate ?? 0);
                    $detail->pb_rate = (float) ($detail->pb_rate ?? 0);
                    $detail->npb_rate = (float) ($detail->npb_rate ?? 0);
                    $detail->session_amount = (float) ($detail->session_amount ?? 0);
                    $detail->hour_amount = 0;
                    $detail->base_amount = 0;
                    
                    // Add formatted amounts
                    $detail->formatted_honor_per_aktivitas = CurrencyHelper::formatRupiah($detail->honor_per_aktivitas);
                    $detail->formatted_cpb_amount = CurrencyHelper::formatRupiah($detail->cpb_amount);
                    $detail->formatted_pb_amount = CurrencyHelper::formatRupiah($detail->pb_amount);
                    $detail->formatted_npb_amount = CurrencyHelper::formatRupiah($detail->npb_amount);
                    $detail->formatted_session_amount = CurrencyHelper::formatRupiah($detail->session_amount);
                    
                    return $detail;
                });

                // Add formatted total honor
                $honorDetail->formatted_total_honor = CurrencyHelper::formatRupiah($honorDetail->total_honor);
            }

            return response()->json([
                'success' => true,
                'data' => $honorDetail,
                'stats' => $stats,
                'tutor' => $tutor
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch monthly detail: ' . $e->getMessage()
            ], 500);
        }
    }

    public function calculateHonor(Request $request, $id_tutor)
    {
        $user = Auth::user();
        
        if (!$user->adminShelter) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        $request->validate([
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2020|max:' . (date('Y') + 1),
            'force_recalculate' => 'nullable|boolean'
        ]);

        $tutor = Tutor::where('id_shelter', $user->adminShelter->shelter->id_shelter)
                     ->findOrFail($id_tutor);

        $month = $request->month;
        $year = $request->year;
        $currentDate = Carbon::now();

        if ($year > $currentDate->year || ($year == $currentDate->year && $month > $currentDate->month)) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot calculate honor for future periods'
            ], 422);
        }

        $existingHonor = TutorHonor::byTutor($id_tutor)->byMonth($month, $year)->first();
        if ($existingHonor && $existingHonor->status === 'paid' && !$request->force_recalculate) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot recalculate paid honor without force flag'
            ], 422);
        }

        try {
            $honor = $this->tutorHonorService->calculateMonthlyHonor($id_tutor, $month, $year);

            // Transform details to ensure proper structure and formatting
            if ($honor && $honor->details) {
                $honor->details->transform(function ($detail) {
                    $detail->cpb_count = (int) ($detail->cpb_count ?? 0);
                    $detail->pb_count = (int) ($detail->pb_count ?? 0);
                    $detail->npb_count = (int) ($detail->npb_count ?? 0);
                    $detail->cpb_amount = (float) ($detail->cpb_amount ?? 0);
                    $detail->pb_amount = (float) ($detail->pb_amount ?? 0);
                    $detail->npb_amount = (float) ($detail->npb_amount ?? 0);
                    $detail->cpb_rate = (float) ($detail->cpb_rate ?? 0);
                    $detail->pb_rate = (float) ($detail->pb_rate ?? 0);
                    $detail->npb_rate = (float) ($detail->npb_rate ?? 0);
                    $detail->session_amount = (float) ($detail->session_amount ?? 0);
                    $detail->hour_amount = 0;
                    $detail->base_amount = 0;
                    
                    // Add formatted amounts
                    $detail->formatted_honor_per_aktivitas = CurrencyHelper::formatRupiah($detail->honor_per_aktivitas);
                    
                    return $detail;
                });

                // Add formatted total honor
                $honor->formatted_total_honor = CurrencyHelper::formatRupiah($honor->total_honor);
            }

            return response()->json([
                'success' => true,
                'message' => 'Honor berhasil dihitung',
                'data' => $honor
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghitung honor: ' . $e->getMessage()
            ], 500);
        }
    }

    public function approveHonor($id_honor)
    {
        $user = Auth::user();
        
        if (!$user->adminShelter) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        $honor = TutorHonor::with('tutor')
                          ->whereHas('tutor', function($query) use ($user) {
                              $query->where('id_shelter', $user->adminShelter->shelter->id_shelter);
                          })
                          ->findOrFail($id_honor);

        if ($honor->status !== 'draft') {
            return response()->json([
                'success' => false,
                'message' => 'Only draft honors can be approved'
            ], 422);
        }

        try {
            $updatedHonor = $this->tutorHonorService->approveHonor($id_honor);
            $updatedHonor->formatted_total_honor = CurrencyHelper::formatRupiah($updatedHonor->total_honor);

            return response()->json([
                'success' => true,
                'message' => 'Honor berhasil disetujui',
                'data' => $updatedHonor
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyetujui honor: ' . $e->getMessage()
            ], 500);
        }
    }

    public function markAsPaid($id_honor)
    {
        $user = Auth::user();
        
        if (!$user->adminShelter) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        $honor = TutorHonor::with('tutor')
                          ->whereHas('tutor', function($query) use ($user) {
                              $query->where('id_shelter', $user->adminShelter->shelter->id_shelter);
                          })
                          ->findOrFail($id_honor);

        if ($honor->status !== 'approved') {
            return response()->json([
                'success' => false,
                'message' => 'Only approved honors can be marked as paid'
            ], 422);
        }

        try {
            $updatedHonor = $this->tutorHonorService->markAsPaid($id_honor);
            $updatedHonor->formatted_total_honor = CurrencyHelper::formatRupiah($updatedHonor->total_honor);

            return response()->json([
                'success' => true,
                'message' => 'Honor berhasil ditandai sebagai dibayar',
                'data' => $updatedHonor
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menandai honor: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getHonorStats(Request $request)
    {
        $user = Auth::user();
        
        if (!$user->adminShelter) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        $request->validate([
            'year' => 'nullable|integer|min:2020|max:' . (date('Y') + 1),
            'month' => 'nullable|integer|min:1|max:12',
            'status' => ['nullable', Rule::in(['draft', 'approved', 'paid'])],
            'tutor_id' => 'nullable|exists:tutor,id_tutor'
        ]);

        try {
            $year = $request->input('year', Carbon::now()->year);
            $month = $request->input('month');
            $status = $request->input('status');
            $tutorId = $request->input('tutor_id');

            $query = TutorHonor::whereHas('tutor', function($q) use ($user) {
                $q->where('id_shelter', $user->adminShelter->shelter->id_shelter);
            })->where('tahun', $year);

            if ($month) {
                $query->where('bulan', $month);
            }

            if ($status) {
                $query->where('status', $status);
            }

            if ($tutorId) {
                $query->where('id_tutor', $tutorId);
            }

            $honors = $query->with('tutor')->get();

            $totalHonorAmount = $honors->sum('total_honor');
            $rataRataHonor = $honors->avg('total_honor');

            $stats = [
                'total_honor' => $totalHonorAmount,
                'total_aktivitas' => $honors->sum('total_aktivitas'),
                'total_tutor' => $honors->groupBy('id_tutor')->count(),
                'rata_rata_honor' => $rataRataHonor,
                'formatted_total_honor' => CurrencyHelper::formatRupiah($totalHonorAmount),
                'formatted_rata_rata_honor' => CurrencyHelper::formatRupiah($rataRataHonor),
                'status_breakdown' => $honors->groupBy('status')->map(function ($group) {
                    $total = $group->sum('total_honor');
                    return [
                        'count' => $group->count(),
                        'total_honor' => $total,
                        'formatted_total_honor' => CurrencyHelper::formatRupiah($total)
                    ];
                }),
                'monthly_breakdown' => $honors->groupBy('bulan')->map(function ($group) {
                    $totalHonor = $group->sum('total_honor');
                    return [
                        'total_honor' => $totalHonor,
                        'total_aktivitas' => $group->sum('total_aktivitas'),
                        'count' => $group->count(),
                        'formatted_total_honor' => CurrencyHelper::formatRupiah($totalHonor)
                    ];
                })
            ];

            return response()->json([
                'success' => true,
                'data' => $stats,
                'period' => compact('year', 'month', 'status', 'tutorId')
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate stats: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getHonorHistory($id_tutor, Request $request)
    {
        $user = Auth::user();
        
        if (!$user->adminShelter) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        $request->validate([
            'start_date' => 'nullable|date|before_or_equal:today',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'status' => ['nullable', Rule::in(['draft', 'approved', 'paid'])],
            'year' => 'nullable|integer|min:2020|max:' . (date('Y') + 1),
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
            'sort_by' => ['nullable', Rule::in(['date', 'amount', 'activities', 'status'])],
            'sort_order' => ['nullable', Rule::in(['asc', 'desc'])],
            'min_amount' => 'nullable|numeric|min:0',
            'max_amount' => 'nullable|numeric|min:0|gte:min_amount',
            'min_activities' => 'nullable|integer|min:0',
            'max_activities' => 'nullable|integer|min:0|gte:min_activities'
        ]);

        $tutor = Tutor::where('id_shelter', $user->adminShelter->shelter->id_shelter)
                     ->findOrFail($id_tutor);

        try {
            $filters = $request->only([
                'start_date', 'end_date', 'status', 'year', 'page', 'per_page',
                'sort_by', 'sort_order', 'min_amount', 'max_amount', 
                'min_activities', 'max_activities'
            ]);

            $honorHistory = $this->tutorHonorService->getHonorByPeriod($id_tutor, $filters);

            // Add formatted amounts to items
            $formattedItems = $honorHistory->getCollection()->map(function ($item) {
                $item->formatted_total_honor = CurrencyHelper::formatRupiah($item->total_honor);
                return $item;
            });

            return response()->json([
                'success' => true,
                'data' => $formattedItems,
                'pagination' => [
                    'current_page' => $honorHistory->currentPage(),
                    'last_page' => $honorHistory->lastPage(),
                    'per_page' => $honorHistory->perPage(),
                    'total' => $honorHistory->total(),
                    'from' => $honorHistory->firstItem(),
                    'to' => $honorHistory->lastItem()
                ],
                'filters' => $filters,
                'tutor' => $tutor
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memuat riwayat honor: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getHonorStatistics($id_tutor, Request $request)
    {
        $user = Auth::user();
        
        if (!$user->adminShelter) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        $request->validate([
            'start_date' => 'nullable|date|before_or_equal:today',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'status' => ['nullable', Rule::in(['draft', 'approved', 'paid'])],
            'year' => 'nullable|integer|min:2020|max:' . (date('Y') + 1)
        ]);

        $tutor = Tutor::where('id_shelter', $user->adminShelter->shelter->id_shelter)
                     ->findOrFail($id_tutor);

        try {
            $filters = $request->only(['start_date', 'end_date', 'status', 'year']);
            $statistics = $this->tutorHonorService->generatePeriodStatistics($id_tutor, $filters);

            return response()->json([
                'success' => true,
                'data' => $statistics,
                'tutor' => $tutor
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memuat statistik honor: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getYearRange($id_tutor)
    {
        $user = Auth::user();
        
        if (!$user->adminShelter) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        $tutor = Tutor::where('id_shelter', $user->adminShelter->shelter->id_shelter)
                     ->findOrFail($id_tutor);

        try {
            $yearRange = $this->tutorHonorService->getYearRange($id_tutor);

            return response()->json([
                'success' => true,
                'data' => $yearRange
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memuat rentang tahun: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getCurrentSettings()
    {
        $user = Auth::user();
        
        if (!$user->adminShelter) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        try {
            $activeSetting = TutorHonorSettings::getActiveSetting();

            if (!$activeSetting) {
                return response()->json([
                    'success' => false,
                    'message' => 'No active honor settings found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $activeSetting
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get current settings: ' . $e->getMessage()
            ], 500);
        }
    }

    public function calculatePreview(Request $request)
    {
        $user = Auth::user();
        
        if (!$user->adminShelter) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        $request->validate([
            'cpb_count' => 'nullable|integer|min:0',
            'pb_count' => 'nullable|integer|min:0',
            'npb_count' => 'nullable|integer|min:0',
            'session_count' => 'nullable|integer|min:0'
        ]);

        try {
            $setting = TutorHonorSettings::getActiveSetting();

            if (!$setting) {
                return response()->json([
                    'success' => false,
                    'message' => 'No honor setting available'
                ], 404);
            }

            $calculation = $setting->calculateHonor(
                $request->input('cpb_count', 0),
                $request->input('pb_count', 0),
                $request->input('npb_count', 0),
                $request->input('session_count', 0)
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'calculation' => $calculation,
                    'setting' => $setting,
                    'formatted_total' => CurrencyHelper::formatRupiah($calculation['total_amount'])
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to calculate preview: ' . $e->getMessage()
            ], 500);
        }
    }
}