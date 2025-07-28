<?php

namespace App\Http\Controllers\API\AdminCabang;

use App\Http\Controllers\Controller;
use App\Models\Anak;
use App\Models\Donatur;
use App\Models\AdminCabang;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminCabangPengajuanDonaturController extends Controller
{
    public function getCpbChildren(Request $request)
    {
        try {
            $adminCabang = AdminCabang::where('user_id', auth()->id())->first();
            if (!$adminCabang) return response()->json(['success' => false, 'message' => 'Admin cabang not found'], 404);

            $query = Anak::with(['keluarga', 'shelter.wilbin', 'anakPendidikan'])
                ->where('status_cpb', 'CPB')
                ->whereNull('id_donatur')
                ->whereIn('status_validasi', ['aktif', 'Aktif'])
                ->whereHas('shelter.wilbin', fn($q) => $q->where('id_kacab', $adminCabang->id_kacab));

            // Add search functionality
            if ($request->has('search') && !empty($request->search)) {
                $searchTerm = trim($request->search);
                $query->where(function($q) use ($searchTerm) {
                    $q->where('full_name', 'LIKE', "%{$searchTerm}%")
                      ->orWhere('nick_name', 'LIKE', "%{$searchTerm}%");
                });
            }

            $children = $query->orderBy('full_name', 'asc')
                            ->paginate($request->get('per_page', 15));

            return response()->json(['success' => true, 'data' => $children]);
        } catch (\Exception $e) {
            \Log::error('Error fetching CPB children: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to fetch CPB children'], 500);
        }
    }

    public function getAvailableDonatur(Request $request)
    {
        try {
            $adminCabang = AdminCabang::where('user_id', auth()->id())->first();
            if (!$adminCabang) return response()->json(['success' => false, 'message' => 'Admin cabang not found'], 404);

            $query = Donatur::with(['kacab', 'wilbin', 'shelter'])
                ->where('id_kacab', $adminCabang->id_kacab)
                ->where(function($q) {
                    $q->where('diperuntukan', 'CPB')
                      ->orWhere('diperuntukan', 'CPB DAN NPB');
                })
                ->withCount('anak'); 

            // Add search functionality for donatur
            if ($request->has('search') && !empty($request->search)) {
                $searchTerm = trim($request->search);
                $query->where(function($q) use ($searchTerm) {
                    $q->where('nama_lengkap', 'LIKE', "%{$searchTerm}%")
                      ->orWhere('no_hp', 'LIKE', "%{$searchTerm}%")
                      ->orWhere('alamat', 'LIKE', "%{$searchTerm}%");
                });
            }

            $donatur = $query->orderBy('nama_lengkap', 'asc')
                           ->paginate($request->get('per_page', 15));

            return response()->json(['success' => true, 'data' => $donatur]);
        } catch (\Exception $e) {
            \Log::error('Error fetching available donatur: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to fetch available donatur'], 500);
        }
    }

    public function assignDonatur(Request $request)
    {
        $request->validate([
            'id_anak' => 'required|exists:anak,id_anak',
            'id_donatur' => 'required|exists:donatur,id_donatur'
        ]);

        try {
            $adminCabang = AdminCabang::where('user_id', auth()->id())->first();
            if (!$adminCabang) return response()->json(['success' => false, 'message' => 'Admin cabang not found'], 404);

            DB::beginTransaction();

            $anak = Anak::with('shelter.wilbin')->find($request->id_anak);
            if (!$anak || $anak->shelter->wilbin->id_kacab !== $adminCabang->id_kacab) {
                return response()->json(['success' => false, 'message' => 'Child not found or not in your jurisdiction'], 404);
            }

            if ($anak->id_donatur) {
                return response()->json(['success' => false, 'message' => 'Child already has a sponsor'], 400);
            }

            $donatur = Donatur::withCount('anak')->find($request->id_donatur);
            if (!$donatur || $donatur->id_kacab !== $adminCabang->id_kacab) {
                return response()->json(['success' => false, 'message' => 'Donatur not found or not in your jurisdiction'], 404);
            }

            if ($donatur->anak_count >= 3) {
                return response()->json(['success' => false, 'message' => 'Donatur has reached maximum sponsored children'], 400);
            }

            $anak->update([
                'id_donatur' => $request->id_donatur,
                'status_cpb' => 'PB',
                'sponsorship_date' => now()
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Donatur successfully assigned to child',
                'data' => $anak->load(['donatur', 'keluarga'])
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error assigning donatur: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to assign donatur'], 500);
        }
    }

    public function getChildDetail($id)
    {
        try {
            $adminCabang = AdminCabang::where('user_id', auth()->id())->first();
            if (!$adminCabang) return response()->json(['success' => false, 'message' => 'Admin cabang not found'], 404);

            $anak = Anak::with([
                'keluarga', 'shelter.wilbin', 'anakPendidikan', 
                'prestasi' => fn($q) => $q->latest()->take(5),
                'Raport' => fn($q) => $q->latest()->take(3)
            ])
            ->whereHas('shelter.wilbin', fn($q) => $q->where('id_kacab', $adminCabang->id_kacab))
            ->find($id);

            if (!$anak) return response()->json(['success' => false, 'message' => 'Child not found'], 404);

            return response()->json(['success' => true, 'data' => $anak]);
        } catch (\Exception $e) {
            \Log::error('Error fetching child detail: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to fetch child detail'], 500);
        }
    }
}