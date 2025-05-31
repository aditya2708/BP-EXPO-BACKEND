<?php

namespace App\Http\Controllers\API\AdminShelter;

use App\Http\Controllers\Controller;
use App\Models\Histori;
use App\Models\Anak;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Http\Resources\Histori\HistoriCollection;

class AdminShelterRiwayatController extends Controller
{
    /**
     * Display a listing of history records for a child
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

        // Check if anak exists and belongs to the shelter
        $anak = Anak::where('id_shelter', $user->adminShelter->id_shelter)
                    ->findOrFail($anakId);

        // Query for histories of this child
        $query = Histori::where('id_anak', $anakId);

        // Filter by jenis_histori if provided
        if ($request->has('jenis_histori')) {
            $query->where('jenis_histori', $request->jenis_histori);
        }

        // Default pagination
        $perPage = $request->per_page ?? 10;

        // Paginate
        $histories = $query->latest()->paginate($perPage);

        // Calculate summary
        $summary = [
            'total_histori' => Histori::where('id_anak', $anakId)->count(),
        ];

        return response()->json([
            'success' => true,
            'message' => 'Daftar Riwayat Anak',
            'data' => HistoriCollection::collection($histories),
            'pagination' => [
                'total' => $histories->total(),
                'per_page' => $histories->perPage(),
                'current_page' => $histories->currentPage(),
                'last_page' => $histories->lastPage(),
                'from' => $histories->firstItem(),
                'to' => $histories->lastItem()
            ],
            'summary' => $summary
        ], 200);
    }

    /**
     * Display the specified history record
     *
     * @param  int  $anakId
     * @param  int  $historiId
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($anakId, $historiId)
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

        // Check if anak exists and belongs to the shelter
        $anak = Anak::where('id_shelter', $user->adminShelter->id_shelter)
                    ->findOrFail($anakId);

        // Find history record for this child
        $histori = Histori::where('id_anak', $anakId)
                          ->findOrFail($historiId);

        return response()->json([
            'success' => true,
            'message' => 'Detail Riwayat',
            'data' => new HistoriCollection($histori)
        ], 200);
    }

    /**
     * Store a newly created history record
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

        // Check if anak exists and belongs to the shelter
        $anak = Anak::where('id_shelter', $user->adminShelter->id_shelter)
                    ->findOrFail($anakId);

        // Validation rules
        $validatedData = $request->validate([
            'jenis_histori' => 'required|string|max:255',
            'nama_histori' => 'required|string|max:255',
            'di_opname' => 'required|string|max:255',
            'dirawat_id' => 'nullable|exists:anak,id_anak',
            'tanggal' => 'nullable|date',
            'foto' => 'nullable|image|max:2048', // max 2MB
            'is_read' => 'nullable|boolean',
        ]);

        // Create Histori record with anak_id
        $histori = new Histori();
        $histori->id_anak = $anakId;
        $histori->fill($validatedData);
        
        // Set default values for nullable fields
        $histori->tanggal = $histori->tanggal ?? now();
        $histori->is_read = $request->has('is_read') ? $request->is_read : false;

        // Save to get the ID first
        $histori->save();

        // Handle foto upload
        if ($request->hasFile('foto')) {
            $file = $request->file('foto');
            $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs("Histori/{$anakId}", $filename, 'public');
            $histori->foto = $filename;
            $histori->save();
        }

        return response()->json([
            'success' => true,
            'message' => 'Riwayat berhasil ditambahkan',
            'data' => new HistoriCollection($histori)
        ], 201);
    }

    /**
     * Update the specified history record
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $anakId
     * @param  int  $historiId
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $anakId, $historiId)
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

        // Check if anak exists and belongs to the shelter
        $anak = Anak::where('id_shelter', $user->adminShelter->id_shelter)
                    ->findOrFail($anakId);

        // Find history record for this child
        $histori = Histori::where('id_anak', $anakId)
                          ->findOrFail($historiId);

        // Validation rules
        $validatedData = $request->validate([
            'jenis_histori' => 'sometimes|string|max:255',
            'nama_histori' => 'sometimes|string|max:255',
            'di_opname' => 'required|string|max:255',
            'dirawat_id' => 'nullable|exists:anak,id_anak',
            'tanggal' => 'nullable|date',
            'foto' => 'nullable|image|max:2048', // max 2MB
            'is_read' => 'nullable|boolean',
        ]);

        // Update Histori record
        $histori->fill($validatedData);

        // Handle foto upload
        if ($request->hasFile('foto')) {
            // Delete old foto if exists
            if ($histori->foto) {
                Storage::disk('public')->delete("Histori/{$anakId}/{$histori->foto}");
            }

            $file = $request->file('foto');
            $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs("Histori/{$anakId}", $filename, 'public');
            $histori->foto = $filename;
        }

        $histori->save();

        return response()->json([
            'success' => true,
            'message' => 'Riwayat berhasil diperbarui',
            'data' => new HistoriCollection($histori)
        ], 200);
    }

    /**
     * Remove the specified history record
     *
     * @param  int  $anakId
     * @param  int  $historiId
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($anakId, $historiId)
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

        // Check if anak exists and belongs to the shelter
        $anak = Anak::where('id_shelter', $user->adminShelter->id_shelter)
                    ->findOrFail($anakId);

        // Find history record for this child
        $histori = Histori::where('id_anak', $anakId)
                          ->findOrFail($historiId);

        // Delete associated foto if exists
        if ($histori->foto) {
            Storage::disk('public')->delete("Histori/{$anakId}/{$histori->foto}");
        }

        // Delete the histori record
        $histori->delete();

        return response()->json([
            'success' => true,
            'message' => 'Riwayat berhasil dihapus'
        ], 200);
    }
}