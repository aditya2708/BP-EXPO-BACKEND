<?php

namespace App\Http\Controllers\API\AdminShelter;

use App\Http\Controllers\Controller;
use App\Models\Anak;
use App\Http\Resources\Anak\AnakCollection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AdminShelterAnakController extends Controller
{
    /**
     * Display a listing of anak.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        // Get the authenticated admin_shelter
        $user = Auth::user();
        
        // Ensure the user has an admin_shelter profile
        if (!$user->adminShelter) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        $query = Anak::where('id_shelter', $user->adminShelter->id_shelter);

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
        $query->with(['kelompok', 'shelter']);

        // Paginate
        $anak = $query->latest()->paginate($perPage);

        // Calculate summary
        $summary = [
            'total' => Anak::where('id_shelter', $user->adminShelter->id_shelter)->count(),
            'anak_aktif' => Anak::where('id_shelter', $user->adminShelter->id_shelter)
                            ->where('status_validasi', 'aktif')
                            ->count(),
            'anak_tidak_aktif' => Anak::where('id_shelter', $user->adminShelter->id_shelter)
                                ->where('status_validasi', 'non-aktif')
                                ->count(),
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
        // Get the authenticated admin_shelter
        $user = Auth::user();
        
        // Ensure the user has an admin_shelter profile
        if (!$user->adminShelter) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        // Find anak by ID and ensure it belongs to the shelter
        $anak = Anak::where('id_shelter', $user->adminShelter->id_shelter)
                    ->with([
                        'keluarga', 
                        'kelompok', 
                        'shelter', 
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
     * Store a newly created anak.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        // Get the authenticated admin_shelter
        $user = Auth::user();
        
        // Ensure the user has an admin_shelter profile
        if (!$user->adminShelter) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        // Validation rules
        $validatedData = $request->validate([
            'id_keluarga' => 'required|exists:keluarga,id_keluarga',
            'id_anak_pend' => 'nullable|exists:anak_pendidikan,id_anak_pend',
            'id_kelompok' => 'nullable|exists:kelompok,id_kelompok',
            'nik_anak' => 'nullable|unique:anak,nik_anak|max:16',
            'anak_ke' => 'nullable|integer',
            'dari_bersaudara' => 'nullable|integer',
            'nick_name' => 'required|string|max:255',
            'full_name' => 'required|string|max:255',
            'agama' => 'required|in:Islam,Kristen,Budha,Hindu,Konghucu',
            'tempat_lahir' => 'required|string|max:255',
            'tanggal_lahir' => 'required|date',
            'jenis_kelamin' => 'sometimes|in:Laki-laki,Perempuan',
            'tinggal_bersama' => 'required|in:Ayah,Ibu,Wali',
            'jenis_anak_binaan' => 'required|in:BPCB,NPB',
            'hafalan' => 'required|in:Tahfidz,Non-Tahfidz',
            'status_validasi' => 'nullable|in:aktif,non-aktif,Ditolak,Ditangguhkan',
            'foto' => 'nullable|image|max:2048', // max 2MB
        ]);

        // Add shelter ID from authenticated user
        $validatedData['id_shelter'] = $user->adminShelter->id_shelter;

        // Create Anak record
        $anak = new Anak($validatedData);

        // Set default status if not provided
        $anak->status_validasi = $validatedData['status_validasi'] ?? 'non-aktif';

        // Determine status_cpb
        $anak->status_cpb = $validatedData['jenis_anak_binaan'] === 'BPCB' 
            ? Anak::STATUS_CPB_BCPB 
            : Anak::STATUS_CPB_NPB;

        // Save to get the ID first
        $anak->save();

        // Handle foto upload
        if ($request->hasFile('foto')) {
            $file = $request->file('foto');
            $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs("Anak/{$anak->id_anak}", $filename, 'public');
            $anak->foto = $filename;
            $anak->save();
        }

        return response()->json([
            'success' => true,
            'message' => 'Anak berhasil ditambahkan',
            'data' => new AnakCollection($anak)
        ], 201);
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
        // Get the authenticated admin_shelter
        $user = Auth::user();
        
        // Ensure the user has an admin_shelter profile
        if (!$user->adminShelter) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        // Find anak by ID and ensure it belongs to the shelter
        $anak = Anak::where('id_shelter', $user->adminShelter->id_shelter)
                    ->findOrFail($id);

        // Validation rules
        $validatedData = $request->validate([
            'id_keluarga' => 'sometimes|exists:keluarga,id_keluarga',
            'id_anak_pend' => 'nullable|exists:anak_pendidikan,id_anak_pend',
            'id_kelompok' => 'nullable|exists:kelompok,id_kelompok',
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
        // Get the authenticated admin_shelter
        $user = Auth::user();
        
        // Ensure the user has an admin_shelter profile
        if (!$user->adminShelter) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        // Find anak by ID and ensure it belongs to the shelter
        $anak = Anak::where('id_shelter', $user->adminShelter->id_shelter)
                    ->findOrFail($id);

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
     * Remove the specified anak.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        // Get the authenticated admin_shelter
        $user = Auth::user();
        
        // Ensure the user has an admin_shelter profile
        if (!$user->adminShelter) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        // Find anak by ID and ensure it belongs to the shelter
        $anak = Anak::where('id_shelter', $user->adminShelter->id_shelter)
                    ->findOrFail($id);

        // Delete associated foto if exists
        if ($anak->foto) {
            Storage::disk('public')->delete("Anak/{$anak->id_anak}/{$anak->foto}");
        }

        // Delete the anak record
        $anak->delete();

        return response()->json([
            'success' => true,
            'message' => 'Anak berhasil dihapus'
        ], 200);
    }
    
}