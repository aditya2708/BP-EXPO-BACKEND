<?php

namespace App\Http\Controllers\API\AdminShelter;

use App\Http\Controllers\Controller;
use App\Services\TutorHonorService;
use App\Models\TutorHonor;
use App\Models\Tutor;
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

            $stats = [
                'total_honor' => $honors->sum('total_honor'),
                'total_aktivitas' => $honors->sum('total_aktivitas'),
                'total_tutor' => $honors->groupBy('id_tutor')->count(),
                'rata_rata_honor' => $honors->avg('total_honor'),
                'status_breakdown' => $honors->groupBy('status')->map->count(),
                'monthly_breakdown' => $honors->groupBy('bulan')->map(function ($group) {
                    return [
                        'total_honor' => $group->sum('total_honor'),
                        'total_aktivitas' => $group->sum('total_aktivitas'),
                        'count' => $group->count()
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

            return response()->json([
                'success' => true,
                'data' => $honorHistory->items(),
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
    
}