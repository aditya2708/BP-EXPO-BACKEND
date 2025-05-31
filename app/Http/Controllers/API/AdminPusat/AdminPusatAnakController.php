<?php

namespace App\Http\Controllers\API\AdminPusat;

use App\Http\Controllers\Controller;
use App\Models\Anak;
use App\Http\Resources\Anak\AnakCollection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AdminPusatAnakController extends Controller
{
    /**
     * Display a listing of anak.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        // Get the authenticated admin_pusat
        $user = Auth::user();
        
        // Ensure the user has an admin_pusat profile
        if ($user->level !== 'admin_pusat') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        $query = Anak::query();

        // Filter by shelter if provided
        if ($request->has('shelter_id')) {
            $query->where('id_shelter', $request->shelter_id);
        }

        // Filter by cabang
        if ($request->has('cabang_id')) {
            $query->whereHas('shelter.wilbin', function($q) use ($request) {
                $q->where('id_kacab', $request->cabang_id);
            });
        }

        // Filter by wilbin
        if ($request->has('wilbin_id')) {
            $query->whereHas('shelter', function($q) use ($request) {
                $q->where('id_wilbin', $request->wilbin_id);
            });
        }

        // Search filter
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                  ->orWhere('nick_name', 'like', "%{$search}%")
                  ->orWhere('nik_anak', 'like', "%{$search}%");
            });
        }

        // Status filter
        if ($request->has('status')) {
            $query->where('status_validasi', $request->status);
        }

        // Default pagination
        $perPage = $request->per_page ?? 10;

        // Load relationships
        $query->with(['kelompok', 'shelter', 'shelter.wilbin', 'shelter.wilbin.kacab']);

        // Paginate
        $anak = $query->latest()->paginate($perPage);

        // Calculate summary
        $summary = [
            'total' => Anak::count(),
            'anak_aktif' => Anak::where('status_validasi', 'aktif')->count(),
            'anak_tidak_aktif' => Anak::where('status_validasi', 'non-aktif')->count(),
            'shelter_count' => Anak::distinct('id_shelter')->count('id_shelter'),
        ];

        return response()->json([
            'success' => true,
            'message' => 'Daftar Anak',
            'data' => AnakCollection::collection($anak),
            'pagination' => [
                'total' => $anak->total(),
                'per_page' => $anak->perPage(),
                'current_page' => $anak->currentPage(),
                'last_page' => $anak->lastPage(),
                'from' => $anak->firstItem(),
                'to' => $anak->lastItem()
            ],
            'summary' => $summary
        ], 200);
    }

    /**
     * Display the specified anak.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        // Get the authenticated admin_pusat
        $user = Auth::user();
        
        // Ensure the user has an admin_pusat profile
        if ($user->level !== 'admin_pusat') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        // Find anak by ID
        $anak = Anak::with([
                'keluarga', 
                'kelompok', 
                'shelter', 
                'shelter.wilbin',
                'shelter.wilbin.kacab',
                'anakPendidikan'
            ])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'message' => 'Detail Anak',
            'data' => new AnakCollection($anak)
        ], 200);
    }

    /**
     * Update the specified anak.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        // Get the authenticated admin_pusat
        $user = Auth::user();
        
        // Ensure the user has an admin_pusat profile
        if ($user->level !== 'admin_pusat') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        // Find anak by ID
        $anak = Anak::findOrFail($id);

        // Validation rules
        $validatedData = $request->validate([
            'id_keluarga' => 'sometimes|exists:keluarga,id_keluarga',
            'id_anak_pend' => 'nullable|exists:anak_pendidikan,id_anak_pend',
            'id_kelompok' => 'nullable|exists:kelompok,id_kelompok',
            'id_shelter' => 'sometimes|exists:shelter,id_shelter',
            'nik_anak' => 'nullable|unique:anak,nik_anak,' . $anak->id_anak . ',id_anak|max:16',
            'anak_ke' => 'nullable|integer',
            'dari_bersaudara' => 'nullable|integer',
            'nick_name' => 'sometimes|string|max:255',
            'full_name' => 'sometimes|string|max:255',
            'agama' => 'sometimes|in:Islam,Kristen,Budha,Hindu,Konghucu',
            'tempat_lahir' => 'sometimes|string|max:255',
            'tanggal_lahir' => 'sometimes|date',
            'jenis_kelamin' => 'sometimes|in:Laki-laki,Perempuan',
            'tinggal_bersama' => 'sometimes|in:Ayah,Ibu,Wali',
            'jenis_anak_binaan' => 'sometimes|in:BPCB,NPB',
            'hafalan' => 'sometimes|in:Tahfidz,Non-Tahfidz',
            'status_validasi' => 'sometimes|in:aktif,non-aktif,Ditolak,Ditangguhkan',
            'foto' => 'nullable|image|max:2048', // max 2MB
        ]);

        // Update Anak record
        $anak->fill($validatedData);

        // Determine status_cpb if jenis_anak_binaan is updated
        if (isset($validatedData['jenis_anak_binaan'])) {
            $anak->status_cpb = $validatedData['jenis_anak_binaan'] === 'BPCB' 
                ? Anak::STATUS_CPB_BCPB 
                : Anak::STATUS_CPB_NPB;
        }

        // Handle foto upload
        if ($request->hasFile('foto')) {
            // Delete old foto if exists
            if ($anak->foto) {
                Storage::disk('public')->delete("Anak/{$anak->id_anak}/{$anak->foto}");
            }

            $file = $request->file('foto');
            $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs("Anak/{$anak->id_anak}", $filename, 'public');
            $anak->foto = $filename;
        }

        $anak->save();

        return response()->json([
            'success' => true,
            'message' => 'Anak berhasil diperbarui',
            'data' => new AnakCollection($anak)
        ], 200);
    }
    
    /**
     * Toggle anak status between 'aktif' and 'non-aktif'.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function toggleStatus($id)
    {
        // Get the authenticated admin_pusat
        $user = Auth::user();
        
        // Ensure the user has an admin_pusat profile
        if ($user->level !== 'admin_pusat') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        // Find anak by ID
        $anak = Anak::findOrFail($id);

        // Toggle status between aktif and non-aktif
        $newStatus = $anak->status_validasi === 'aktif' ? 'non-aktif' : 'aktif';
        $anak->status_validasi = $newStatus;
        $anak->save();

        return response()->json([
            'success' => true,
            'message' => 'Status anak berhasil diubah menjadi ' . $newStatus,
            'data' => [
                'id_anak' => $anak->id_anak,
                'status_validasi' => $anak->status_validasi
            ]
        ], 200);
    }

    /**
     * Get summary statistics of children.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getSummary()
    {
        // Get the authenticated admin_pusat
        $user = Auth::user();
        
        // Ensure the user has an admin_pusat profile
        if ($user->level !== 'admin_pusat') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        $summary = [
            'total_anak' => Anak::count(),
            'anak_aktif' => Anak::where('status_validasi', 'aktif')->count(),
            'anak_tidak_aktif' => Anak::where('status_validasi', 'non-aktif')->count(),
            'anak_tahfidz' => Anak::where('hafalan', 'Tahfidz')->count(),
            'anak_non_tahfidz' => Anak::where('hafalan', 'Non-Tahfidz')->count(),
            'anak_bpcb' => Anak::where('jenis_anak_binaan', 'BPCB')->count(),
            'anak_npb' => Anak::where('jenis_anak_binaan', 'NPB')->count(),
            'shelter_count' => Anak::distinct('id_shelter')->count('id_shelter'),
            'cabang_count' => Anak::join('shelter', 'anak.id_shelter', '=', 'shelter.id_shelter')
                            ->join('wilbin', 'shelter.id_wilbin', '=', 'wilbin.id_wilbin')
                            ->distinct('wilbin.id_kacab')
                            ->count('wilbin.id_kacab'),
        ];

        return response()->json([
            'success' => true,
            'message' => 'Statistik Anak',
            'data' => $summary
        ], 200);
    }
}