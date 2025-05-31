<?php

namespace App\Http\Controllers\API\AdminShelter;

use App\Http\Controllers\Controller;
use App\Models\Prestasi;
use App\Models\Anak;
use App\Http\Resources\Prestasi\PrestasiCollection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AdminShelterPrestasiController extends Controller
{
    /**
     * Display a listing of prestasi for a specific child.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $anakId
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request, $anakId)
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

        // Check if this child belongs to the admin's shelter
        $anak = Anak::where('id_shelter', $user->adminShelter->id_shelter)
                    ->where('id_anak', $anakId)
                    ->first();
        
        if (!$anak) {
            return response()->json([
                'success' => false,
                'message' => 'Child not found or not associated with your shelter'
            ], 404);
        }

        $query = Prestasi::where('id_anak', $anakId);

        // Filter berdasarkan jenis_prestasi jika ada parameter
        if ($request->has('jenis_prestasi')) {
            $query->where('jenis_prestasi', $request->jenis_prestasi);
        }

        // Filter berdasarkan level_prestasi jika ada parameter
        if ($request->has('level_prestasi')) {
            $query->where('level_prestasi', $request->level_prestasi);
        }

        // Default pagination
        $perPage = $request->per_page ?? 10;

        // Eager loading with anak
        $query->with('anak');

        // Paginate
        $prestasi = $query->latest()->paginate($perPage);

        // Calculate summary
        $summary = [
            'total_prestasi' => Prestasi::where('id_anak', $anakId)->count(),
        ];

        return response()->json([
            'success' => true,
            'message' => 'Daftar Prestasi Anak',
            'data' => PrestasiCollection::collection($prestasi),
            'pagination' => [
                'total' => $prestasi->total(),
                'per_page' => $prestasi->perPage(),
                'current_page' => $prestasi->currentPage(),
                'last_page' => $prestasi->lastPage(),
                'from' => $prestasi->firstItem(),
                'to' => $prestasi->lastItem()
            ],
            'summary' => $summary
        ], 200);
    }

    /**
     * Display the specified prestasi.
     *
     * @param  int  $anakId
     * @param  int  $prestasiId
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($anakId, $prestasiId)
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

        // Check if this child belongs to the admin's shelter
        $anak = Anak::where('id_shelter', $user->adminShelter->id_shelter)
                    ->where('id_anak', $anakId)
                    ->first();
        
        if (!$anak) {
            return response()->json([
                'success' => false,
                'message' => 'Child not found or not associated with your shelter'
            ], 404);
        }

        // Find prestasi and ensure it belongs to the specified child
        $prestasi = Prestasi::where('id_anak', $anakId)
                            ->where('id_prestasi', $prestasiId)
                            ->with('anak')
                            ->firstOrFail();

        // Mark as read if not already read
        if (!$prestasi->is_read) {
            $prestasi->is_read = true;
            $prestasi->save();
        }

        return response()->json([
            'success' => true,
            'message' => 'Detail Prestasi',
            'data' => new PrestasiCollection($prestasi)
        ], 200);
    }

    /**
     * Store a newly created prestasi.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $anakId
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request, $anakId)
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

        // Check if this child belongs to the admin's shelter
        $anak = Anak::where('id_shelter', $user->adminShelter->id_shelter)
                    ->where('id_anak', $anakId)
                    ->first();
        
        if (!$anak) {
            return response()->json([
                'success' => false,
                'message' => 'Child not found or not associated with your shelter'
            ], 404);
        }

        // Validation rules
        $validatedData = $request->validate([
            'jenis_prestasi' => 'required|string|max:255',
            'level_prestasi' => 'required|string|max:255',
            'nama_prestasi' => 'required|string|max:255',
            'foto' => 'nullable|image|max:2048', // max 2MB
            'tgl_upload' => 'nullable|date',
            'is_read' => 'nullable|boolean'
        ]);

        // Create Prestasi record
        $prestasi = new Prestasi($validatedData);
        $prestasi->id_anak = $anakId;
        $prestasi->tgl_upload = $validatedData['tgl_upload'] ?? now();
        $prestasi->is_read = $validatedData['is_read'] ?? false;
        
        // Save to get the ID first
        $prestasi->save();

        // Handle foto upload
        if ($request->hasFile('foto')) {
            $file = $request->file('foto');
            $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs("Prestasi/{$anakId}", $filename, 'public');
            $prestasi->foto = $filename;
            $prestasi->save();
        }

        return response()->json([
            'success' => true,
            'message' => 'Prestasi berhasil ditambahkan',
            'data' => new PrestasiCollection($prestasi)
        ], 201);
    }

    /**
     * Update the specified prestasi.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $anakId
     * @param  int  $prestasiId
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $anakId, $prestasiId)
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

        // Check if this child belongs to the admin's shelter
        $anak = Anak::where('id_shelter', $user->adminShelter->id_shelter)
                    ->where('id_anak', $anakId)
                    ->first();
        
        if (!$anak) {
            return response()->json([
                'success' => false,
                'message' => 'Child not found or not associated with your shelter'
            ], 404);
        }

        // Find prestasi and ensure it belongs to the specified child
        $prestasi = Prestasi::where('id_anak', $anakId)
                            ->where('id_prestasi', $prestasiId)
                            ->firstOrFail();

        // Validation rules
        $validatedData = $request->validate([
            'jenis_prestasi' => 'sometimes|string|max:255',
            'level_prestasi' => 'sometimes|string|max:255',
            'nama_prestasi' => 'sometimes|string|max:255',
            'foto' => 'nullable|image|max:2048', // max 2MB
            'tgl_upload' => 'nullable|date',
            'is_read' => 'nullable|boolean'
        ]);

        // Update Prestasi record
        $prestasi->fill($validatedData);

        // Handle foto upload
        if ($request->hasFile('foto')) {
            // Delete old foto if exists
            if ($prestasi->foto) {
                Storage::disk('public')->delete("Prestasi/{$anakId}/{$prestasi->foto}");
            }

            $file = $request->file('foto');
            $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs("Prestasi/{$anakId}", $filename, 'public');
            $prestasi->foto = $filename;
        }

        $prestasi->save();

        return response()->json([
            'success' => true,
            'message' => 'Prestasi berhasil diperbarui',
            'data' => new PrestasiCollection($prestasi)
        ], 200);
    }

    /**
     * Remove the specified prestasi.
     *
     * @param  int  $anakId
     * @param  int  $prestasiId
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($anakId, $prestasiId)
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

        // Check if this child belongs to the admin's shelter
        $anak = Anak::where('id_shelter', $user->adminShelter->id_shelter)
                    ->where('id_anak', $anakId)
                    ->first();
        
        if (!$anak) {
            return response()->json([
                'success' => false,
                'message' => 'Child not found or not associated with your shelter'
            ], 404);
        }

        // Find prestasi and ensure it belongs to the specified child
        $prestasi = Prestasi::where('id_anak', $anakId)
                            ->where('id_prestasi', $prestasiId)
                            ->firstOrFail();

        // Delete associated foto if exists
        if ($prestasi->foto) {
            Storage::disk('public')->delete("Prestasi/{$anakId}/{$prestasi->foto}");
        }

        // Delete the prestasi record
        $prestasi->delete();

        return response()->json([
            'success' => true,
            'message' => 'Prestasi berhasil dihapus'
        ], 200);
    }
}