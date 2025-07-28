<?php

namespace App\Http\Controllers\API\AdminShelter;

use App\Http\Controllers\Controller;
use App\Models\Keuangan;
use App\Models\Anak;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class AdminShelterKeuanganController extends Controller
{
    /**
     * Get list of keuangan for shelter's children
     */
    public function index(Request $request)
    {
        try {
            $user = auth()->user();
            $adminShelter = $user->adminShelter;
            
            $query = Keuangan::with(['anak' => function($query) {
                $query->select('id_anak', 'full_name', 'nick_name', 'foto');
            }])
            ->whereHas('anak', function($query) use ($adminShelter) {
                $query->where('id_shelter', $adminShelter->id_shelter);
            });

            // Filter by search
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->whereHas('anak', function($q) use ($search) {
                    $q->where('full_name', 'like', "%{$search}%")
                      ->orWhere('nick_name', 'like', "%{$search}%");
                });
            }

            // Filter by semester
            if ($request->has('semester') && $request->semester) {
                $query->where('semester', $request->semester);
            }

            // Filter by tingkat sekolah
            if ($request->has('tingkat_sekolah') && $request->tingkat_sekolah) {
                $query->where('tingkat_sekolah', $request->tingkat_sekolah);
            }

            $keuangan = $query->orderBy('created_at', 'desc')
                             ->paginate($request->get('per_page', 15));

            // Add calculated totals to each item
            $keuangan->getCollection()->transform(function ($item) {
                $item->total_kebutuhan = $item->total_kebutuhan;
                $item->total_bantuan = $item->total_bantuan;
                $item->sisa_tagihan = $item->sisa_tagihan;
                $item->is_lunas = $item->is_lunas;
                return $item;
            });

            return response()->json([
                'message' => 'Keuangan data retrieved successfully',
                'data' => $keuangan
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve keuangan data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create new keuangan record
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'id_anak' => 'required|exists:anak,id_anak',
                'tingkat_sekolah' => 'required|string|max:50',
                'semester' => 'required|string|max:20',
                'bimbel' => 'nullable|numeric|min:0|max:999999999.99',
                'eskul_dan_keagamaan' => 'nullable|numeric|min:0|max:999999999.99',
                'laporan' => 'nullable|numeric|min:0|max:999999999.99',
                'uang_tunai' => 'nullable|numeric|min:0|max:999999999.99',
                'donasi' => 'nullable|numeric|min:0|max:999999999.99',
                'subsidi_infak' => 'nullable|numeric|min:0|max:999999999.99'
            ], [
                'id_anak.required' => 'Anak harus dipilih',
                'id_anak.exists' => 'Anak tidak ditemukan',
                'tingkat_sekolah.required' => 'Tingkat sekolah harus dipilih',
                'semester.required' => 'Semester harus dipilih',
                '*.numeric' => 'Nilai harus berupa angka',
                '*.min' => 'Nilai tidak boleh negatif',
                '*.max' => 'Nilai terlalu besar'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = auth()->user();
            $adminShelter = $user->adminShelter;

            // Verify that the child belongs to this shelter
            $anak = Anak::where('id_anak', $request->id_anak)
                        ->where('id_shelter', $adminShelter->id_shelter)
                        ->first();

            if (!$anak) {
                return response()->json([
                    'message' => 'Anak not found or not in your shelter'
                ], 404);
            }

            // Check if keuangan already exists for this child and semester
            $existingKeuangan = Keuangan::where('id_anak', $request->id_anak)
                                       ->where('semester', $request->semester)
                                       ->where('tingkat_sekolah', $request->tingkat_sekolah)
                                       ->first();

            if ($existingKeuangan) {
                return response()->json([
                    'message' => 'Keuangan record already exists for this child and semester'
                ], 409);
            }

            // Convert empty strings to null for numeric fields
            $data = $request->all();
            $numericFields = ['bimbel', 'eskul_dan_keagamaan', 'laporan', 'uang_tunai', 'donasi', 'subsidi_infak'];
            foreach ($numericFields as $field) {
                if (isset($data[$field]) && ($data[$field] === '' || $data[$field] === null)) {
                    $data[$field] = 0;
                }
            }

            $keuangan = Keuangan::create($data);
            $keuangan->load('anak');
            
            // Add calculated totals
            $keuangan->total_kebutuhan = $keuangan->total_kebutuhan;
            $keuangan->total_bantuan = $keuangan->total_bantuan;
            $keuangan->sisa_tagihan = $keuangan->sisa_tagihan;
            $keuangan->is_lunas = $keuangan->is_lunas;

            return response()->json([
                'message' => 'Keuangan created successfully',
                'data' => $keuangan
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create keuangan record',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get specific keuangan record
     */
    public function show($id)
    {
        try {
            $user = auth()->user();
            $adminShelter = $user->adminShelter;

            $keuangan = Keuangan::with(['anak' => function($query) {
                $query->select('id_anak', 'full_name', 'nick_name', 'foto', 'id_donatur');
            }])
            ->whereHas('anak', function($query) use ($adminShelter) {
                $query->where('id_shelter', $adminShelter->id_shelter);
            })
            ->find($id);

            if (!$keuangan) {
                return response()->json([
                    'message' => 'Keuangan record not found'
                ], 404);
            }

            // Add calculated totals
            $keuangan->total_kebutuhan = $keuangan->total_kebutuhan;
            $keuangan->total_bantuan = $keuangan->total_bantuan;
            $keuangan->sisa_tagihan = $keuangan->sisa_tagihan;
            $keuangan->is_lunas = $keuangan->is_lunas;

            return response()->json([
                'message' => 'Keuangan detail retrieved successfully',
                'data' => $keuangan
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve keuangan detail',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update keuangan record
     */
    public function update(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'tingkat_sekolah' => 'required|string|max:50',
                'semester' => 'required|string|max:20',
                'bimbel' => 'nullable|numeric|min:0|max:999999999.99',
                'eskul_dan_keagamaan' => 'nullable|numeric|min:0|max:999999999.99',
                'laporan' => 'nullable|numeric|min:0|max:999999999.99',
                'uang_tunai' => 'nullable|numeric|min:0|max:999999999.99',
                'donasi' => 'nullable|numeric|min:0|max:999999999.99',
                'subsidi_infak' => 'nullable|numeric|min:0|max:999999999.99'
            ], [
                'tingkat_sekolah.required' => 'Tingkat sekolah harus dipilih',
                'semester.required' => 'Semester harus dipilih',
                '*.numeric' => 'Nilai harus berupa angka',
                '*.min' => 'Nilai tidak boleh negatif',
                '*.max' => 'Nilai terlalu besar'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = auth()->user();
            $adminShelter = $user->adminShelter;

            $keuangan = Keuangan::whereHas('anak', function($query) use ($adminShelter) {
                $query->where('id_shelter', $adminShelter->id_shelter);
            })->find($id);

            if (!$keuangan) {
                return response()->json([
                    'message' => 'Keuangan record not found'
                ], 404);
            }

            // Convert empty strings to null for numeric fields
            $data = $request->all();
            $numericFields = ['bimbel', 'eskul_dan_keagamaan', 'laporan', 'uang_tunai', 'donasi', 'subsidi_infak'];
            foreach ($numericFields as $field) {
                if (isset($data[$field]) && ($data[$field] === '' || $data[$field] === null)) {
                    $data[$field] = 0;
                }
            }

            $keuangan->update($data);
            $keuangan->load('anak');
            
            // Add calculated totals
            $keuangan->total_kebutuhan = $keuangan->total_kebutuhan;
            $keuangan->total_bantuan = $keuangan->total_bantuan;
            $keuangan->sisa_tagihan = $keuangan->sisa_tagihan;
            $keuangan->is_lunas = $keuangan->is_lunas;

            return response()->json([
                'message' => 'Keuangan updated successfully',
                'data' => $keuangan
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update keuangan record',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete keuangan record
     */
    public function destroy($id)
    {
        try {
            $user = auth()->user();
            $adminShelter = $user->adminShelter;

            $keuangan = Keuangan::whereHas('anak', function($query) use ($adminShelter) {
                $query->where('id_shelter', $adminShelter->id_shelter);
            })->find($id);

            if (!$keuangan) {
                return response()->json([
                    'message' => 'Keuangan record not found'
                ], 404);
            }

            $keuangan->delete();

            return response()->json([
                'message' => 'Keuangan deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete keuangan record',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get keuangan by child ID
     */
    public function getByChild($childId)
    {
        try {
            $user = auth()->user();
            $adminShelter = $user->adminShelter;

            // Verify child belongs to this shelter
            $anak = Anak::where('id_anak', $childId)
                        ->where('id_shelter', $adminShelter->id_shelter)
                        ->first();

            if (!$anak) {
                return response()->json([
                    'message' => 'Child not found or not in your shelter'
                ], 404);
            }

            $keuangan = Keuangan::where('id_anak', $childId)
                               ->orderBy('created_at', 'desc')
                               ->get();

            // Add calculated totals to each item
            $keuangan->transform(function ($item) {
                $item->total_kebutuhan = $item->total_kebutuhan;
                $item->total_bantuan = $item->total_bantuan;
                $item->sisa_tagihan = $item->sisa_tagihan;
                $item->is_lunas = $item->is_lunas;
                return $item;
            });

            return response()->json([
                'message' => 'Child keuangan data retrieved successfully',
                'data' => $keuangan
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve child keuangan data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get statistics for keuangan
     */
    public function getStatistics()
    {
        try {
            $user = auth()->user();
            $adminShelter = $user->adminShelter;

            $stats = Keuangan::whereHas('anak', function($query) use ($adminShelter) {
                $query->where('id_shelter', $adminShelter->id_shelter);
            })
            ->selectRaw('
                COUNT(*) as total_records,
                SUM(COALESCE(bimbel, 0) + COALESCE(eskul_dan_keagamaan, 0) + COALESCE(laporan, 0) + COALESCE(uang_tunai, 0)) as total_kebutuhan,
                SUM(COALESCE(donasi, 0) + COALESCE(subsidi_infak, 0)) as total_bantuan,
                AVG(COALESCE(bimbel, 0) + COALESCE(eskul_dan_keagamaan, 0) + COALESCE(laporan, 0) + COALESCE(uang_tunai, 0)) as avg_kebutuhan
            ')
            ->first();

            $semesterStats = Keuangan::whereHas('anak', function($query) use ($adminShelter) {
                $query->where('id_shelter', $adminShelter->id_shelter);
            })
            ->selectRaw('semester, COUNT(*) as count')
            ->groupBy('semester')
            ->orderBy('semester')
            ->get();

            return response()->json([
                'message' => 'Statistics retrieved successfully',
                'data' => [
                    'general' => $stats,
                    'by_semester' => $semesterStats
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}