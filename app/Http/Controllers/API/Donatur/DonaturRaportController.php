<?php

namespace App\Http\Controllers\Api\Donatur;

use App\Http\Controllers\Controller;
use App\Models\Raport;
use App\Models\Anak;
use App\Models\Donatur;
use Illuminate\Http\Request;

class DonaturRaportController extends Controller
{
    /**
     * Get raport list for specific child
     */
    public function index(Request $request, $childId)
    {
        try {
            $donatur = Donatur::where('id_users', $request->user()->id_users)->first();
            
            if (!$donatur) {
                return response()->json([
                    'success' => false,
                    'message' => 'Donatur profile not found'
                ], 404);
            }

            // Verify child belongs to this donatur
            $child = Anak::where('id_anak', $childId)
                ->where('id_donatur', $donatur->id_donatur)
                ->first();

            if (!$child) {
                return response()->json([
                    'success' => false,
                    'message' => 'Child not found or not sponsored by you'
                ], 404);
            }

            $raportList = Raport::where('id_anak', $childId)
                ->where('status', Raport::STATUS_PUBLISHED)
                ->with(['anak', 'semester'])
                ->orderBy('tanggal_terbit', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $raportList
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve reports',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get specific raport details
     */
    public function show(Request $request, $childId, $raportId)
    {
        try {
            $donatur = Donatur::where('id_users', $request->user()->id_users)->first();
            
            if (!$donatur) {
                return response()->json([
                    'success' => false,
                    'message' => 'Donatur profile not found'
                ], 404);
            }

            // Verify child belongs to this donatur
            $child = Anak::where('id_anak', $childId)
                ->where('id_donatur', $donatur->id_donatur)
                ->first();

            if (!$child) {
                return response()->json([
                    'success' => false,
                    'message' => 'Child not found or not sponsored by you'
                ], 404);
            }

            $raport = Raport::where('id_raport', $raportId)
                ->where('id_anak', $childId)
                ->where('status', Raport::STATUS_PUBLISHED)
                ->with(['anak', 'semester', 'raportDetail'])
                ->first();

            if (!$raport) {
                return response()->json([
                    'success' => false,
                    'message' => 'Report not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $raport
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve report details',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get raport summary statistics for child
     */
    public function summary(Request $request, $childId)
    {
        try {
            $donatur = Donatur::where('id_users', $request->user()->id_users)->first();
            
            if (!$donatur) {
                return response()->json([
                    'success' => false,
                    'message' => 'Donatur profile not found'
                ], 404);
            }

            // Verify child belongs to this donatur
            $child = Anak::where('id_anak', $childId)
                ->where('id_donatur', $donatur->id_donatur)
                ->first();

            if (!$child) {
                return response()->json([
                    'success' => false,
                    'message' => 'Child not found or not sponsored by you'
                ], 404);
            }

            $raports = Raport::where('id_anak', $childId)
                ->where('status', Raport::STATUS_PUBLISHED)
                ->with(['semester', 'raportDetail'])
                ->orderBy('tanggal_terbit', 'desc')
                ->get();

            $summary = [
                'total_raports' => $raports->count(),
                'average_attendance' => $raports->avg('persentase_kehadiran'),
                'latest_ranking' => $raports->first()?->ranking,
                'progress_trend' => $this->calculateProgressTrend($raports)
            ];

            return response()->json([
                'success' => true,
                'data' => $summary
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve report summary',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Calculate progress trend from raports
     */
    private function calculateProgressTrend($raports)
    {
        if ($raports->count() < 2) {
            return 'insufficient_data';
        }

        $latest = $raports->first();
        $previous = $raports->skip(1)->first();

        if (!$latest || !$previous) {
            return 'insufficient_data';
        }

        $latestAvg = $latest->raportDetail->avg('nilai_akhir');
        $previousAvg = $previous->raportDetail->avg('nilai_akhir');

        if ($latestAvg > $previousAvg) {
            return 'improving';
        } elseif ($latestAvg < $previousAvg) {
            return 'declining';
        } else {
            return 'stable';
        }
    }
}