<?php

namespace App\Http\Controllers\API\Donatur;

use App\Http\Controllers\Controller;
use App\Models\Keuangan;
use App\Models\Anak;
use Illuminate\Http\Request;

class DonaturKeuanganController extends Controller
{
    /**
     * Get billing for donatur's sponsored children
     */
    public function index(Request $request)
    {
        try {
            $user = auth()->user();
            $donatur = $user->donatur;

            $query = Keuangan::with(['anak' => function($query) {
                $query->select('id_anak', 'full_name', 'nick_name', 'foto', 'id_donatur');
            }])
            ->whereHas('anak', function($query) use ($donatur) {
                $query->where('id_donatur', $donatur->id_donatur);
            });

            // Filter by semester
            if ($request->has('semester') && $request->semester) {
                $query->where('semester', $request->semester);
            }

            // Filter by child
            if ($request->has('child_id') && $request->child_id) {
                $query->where('id_anak', $request->child_id);
            }

            // Filter by status (paid/unpaid based on difference between kebutuhan and bantuan)
            if ($request->has('status') && $request->status) {
                if ($request->status === 'unpaid') {
                    $query->whereRaw('(bimbel + eskul_dan_keagamaan + laporan + uang_tunai) > (donasi + subsidi_infak)');
                } elseif ($request->status === 'paid') {
                    $query->whereRaw('(bimbel + eskul_dan_keagamaan + laporan + uang_tunai) <= (donasi + subsidi_infak)');
                }
            }

            $billing = $query->orderBy('created_at', 'desc')
                            ->paginate($request->get('per_page', 15));

            // Calculate totals for each record
            $billing->getCollection()->transform(function($item) {
                $item->total_kebutuhan = $item->bimbel + $item->eskul_dan_keagamaan + $item->laporan + $item->uang_tunai;
                $item->total_bantuan = $item->donasi + $item->subsidi_infak;
                $item->sisa_tagihan = max(0, $item->total_kebutuhan - $item->total_bantuan);
                $item->status_pembayaran = $item->total_kebutuhan <= $item->total_bantuan ? 'paid' : 'unpaid';
                return $item;
            });

            return response()->json([
                'message' => 'Billing data retrieved successfully',
                'data' => $billing
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve billing data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get specific billing detail
     */
    public function show($id)
    {
        try {
            $user = auth()->user();
            $donatur = $user->donatur;

            $billing = Keuangan::with(['anak' => function($query) {
                $query->select('id_anak', 'full_name', 'nick_name', 'foto', 'id_donatur', 'tempat_lahir', 'tanggal_lahir');
            }])
            ->whereHas('anak', function($query) use ($donatur) {
                $query->where('id_donatur', $donatur->id_donatur);
            })
            ->find($id);

            if (!$billing) {
                return response()->json([
                    'message' => 'Billing record not found'
                ], 404);
            }

            // Calculate totals
            $billing->total_kebutuhan = $billing->bimbel + $billing->eskul_dan_keagamaan + $billing->laporan + $billing->uang_tunai;
            $billing->total_bantuan = $billing->donasi + $billing->subsidi_infak;
            $billing->sisa_tagihan = max(0, $billing->total_kebutuhan - $billing->total_bantuan);
            $billing->status_pembayaran = $billing->total_kebutuhan <= $billing->total_bantuan ? 'paid' : 'unpaid';

            return response()->json([
                'message' => 'Billing detail retrieved successfully',
                'data' => $billing
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve billing detail',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get billing for specific child
     */
    public function getByChild($childId)
    {
        try {
            $user = auth()->user();
            $donatur = $user->donatur;

            // Verify child is sponsored by this donatur
            $anak = Anak::where('id_anak', $childId)
                        ->where('id_donatur', $donatur->id_donatur)
                        ->first();

            if (!$anak) {
                return response()->json([
                    'message' => 'Child not found or not sponsored by you'
                ], 404);
            }

            $billing = Keuangan::where('id_anak', $childId)
                              ->orderBy('created_at', 'desc')
                              ->get();

            // Calculate totals for each record
            $billing->transform(function($item) {
                $item->total_kebutuhan = $item->bimbel + $item->eskul_dan_keagamaan + $item->laporan + $item->uang_tunai;
                $item->total_bantuan = $item->donasi + $item->subsidi_infak;
                $item->sisa_tagihan = max(0, $item->total_kebutuhan - $item->total_bantuan);
                $item->status_pembayaran = $item->total_kebutuhan <= $item->total_bantuan ? 'paid' : 'unpaid';
                return $item;
            });

            return response()->json([
                'message' => 'Child billing data retrieved successfully',
                'data' => [
                    'child' => $anak,
                    'billing' => $billing
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve child billing data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get billing summary for donatur
     */
    public function getSummary()
    {
        try {
            $user = auth()->user();
            $donatur = $user->donatur;

            $summary = Keuangan::whereHas('anak', function($query) use ($donatur) {
                $query->where('id_donatur', $donatur->id_donatur);
            })
            ->selectRaw('
                COUNT(*) as total_bills,
                SUM(bimbel + eskul_dan_keagamaan + laporan + uang_tunai) as total_kebutuhan,
                SUM(donasi + subsidi_infak) as total_bantuan,
                SUM(CASE WHEN (bimbel + eskul_dan_keagamaan + laporan + uang_tunai) > (donasi + subsidi_infak) THEN 1 ELSE 0 END) as unpaid_bills,
                SUM(CASE WHEN (bimbel + eskul_dan_keagamaan + laporan + uang_tunai) <= (donasi + subsidi_infak) THEN 1 ELSE 0 END) as paid_bills
            ')
            ->first();

            $summary->total_sisa_tagihan = max(0, $summary->total_kebutuhan - $summary->total_bantuan);

            // Get recent billing
            $recentBilling = Keuangan::with(['anak' => function($query) {
                $query->select('id_anak', 'full_name', 'nick_name');
            }])
            ->whereHas('anak', function($query) use ($donatur) {
                $query->where('id_donatur', $donatur->id_donatur);
            })
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

            // Calculate totals for recent bills
            $recentBilling->transform(function($item) {
                $item->total_kebutuhan = $item->bimbel + $item->eskul_dan_keagamaan + $item->laporan + $item->uang_tunai;
                $item->total_bantuan = $item->donasi + $item->subsidi_infak;
                $item->sisa_tagihan = max(0, $item->total_kebutuhan - $item->total_bantuan);
                $item->status_pembayaran = $item->total_kebutuhan <= $item->total_bantuan ? 'paid' : 'unpaid';
                return $item;
            });

            // Get sponsored children count
            $sponsoredChildren = Anak::where('id_donatur', $donatur->id_donatur)->count();

            return response()->json([
                'message' => 'Billing summary retrieved successfully',
                'data' => [
                    'summary' => $summary,
                    'recent_billing' => $recentBilling,
                    'sponsored_children_count' => $sponsoredChildren
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve billing summary',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get semester options for filter
     */
    public function getSemesters()
    {
        try {
            $user = auth()->user();
            $donatur = $user->donatur;

            $semesters = Keuangan::whereHas('anak', function($query) use ($donatur) {
                $query->where('id_donatur', $donatur->id_donatur);
            })
            ->distinct()
            ->pluck('semester')
            ->sort()
            ->values();

            return response()->json([
                'message' => 'Semesters retrieved successfully',
                'data' => $semesters
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve semesters',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}