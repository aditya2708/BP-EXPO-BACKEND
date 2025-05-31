<?php

namespace App\Http\Controllers\API\AdminShelter;

use App\Models\Raport;
use App\Models\Anak;
use App\Models\FotoRapor;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Resources\Raport\RaportCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AdminShelterRaportController extends Controller
{
    /**
     * Display a listing of raports for a specific child.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $childId
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request, $childId)
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
        
        // Find the child and ensure it belongs to the shelter
        $anak = Anak::where('id_anak', $childId)
                    ->where('id_shelter', $user->adminShelter->id_shelter)
                    ->firstOrFail();
        
        $query = Raport::where('id_anak', $childId);
        
        // Filter berdasarkan semester
        if ($request->has('semester')) {
            $query->where('semester', $request->semester);
        }
        
        // Filter berdasarkan tingkat
        if ($request->has('tingkat')) {
            $query->where('tingkat', $request->tingkat);
        }
        
        // Filter berdasarkan tahun
        if ($request->has('tahun')) {
            $query->whereYear('tanggal', $request->tahun);
        }
        
        // Eager loading and paginate
        $raports = $query->with(['anak', 'fotoRapor'])
                         ->latest()
                         ->paginate($request->per_page ?? 10);
        
        // Get summary data
        $summary = [
            'total_raport' => Raport::where('id_anak', $childId)->count(),
            'semester_count' => Raport::where('id_anak', $childId)
                               ->distinct('semester')
                               ->count('semester'),
        ];
        
        return response()->json([
            'success' => true,
            'message' => 'Daftar Raport',
            'data' => RaportCollection::collection($raports),
            'pagination' => [
                'total' => $raports->total(),
                'per_page' => $raports->perPage(),
                'current_page' => $raports->currentPage(),
                'last_page' => $raports->lastPage(),
                'from' => $raports->firstItem(),
                'to' => $raports->lastItem()
            ],
            'summary' => $summary
        ], 200);
    }
    
    /**
     * Display the specified raport.
     *
     * @param  int  $childId
     * @param  int  $raportId
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($childId, $raportId)
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
        
        // Find the child and ensure it belongs to the shelter
        $anak = Anak::where('id_anak', $childId)
                    ->where('id_shelter', $user->adminShelter->id_shelter)
                    ->firstOrFail();
        
        // Find raport by ID and ensure it belongs to the child
        $raport = Raport::where('id_raport', $raportId)
                        ->where('id_anak', $childId)
                        ->with(['anak', 'fotoRapor'])
                        ->firstOrFail();
        
        return response()->json([
            'success' => true,
            'message' => 'Detail Raport',
            'data' => new RaportCollection($raport)
        ], 200);
    }
    
    /**
     * Store a newly created raport.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $childId
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request, $childId)
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
        
        // Find the child and ensure it belongs to the shelter
        $anak = Anak::where('id_anak', $childId)
                    ->where('id_shelter', $user->adminShelter->id_shelter)
                    ->firstOrFail();
        
        // Validation rules
        $validatedData = $request->validate([
            'tingkat' => 'required|string|max:50',
            'kelas' => 'required|string|max:50',
            'nilai_max' => 'nullable|numeric',
            'nilai_min' => 'nullable|numeric',
            'nilai_rata_rata' => 'nullable|numeric',
            'semester' => 'required|string|max:20',
            'tanggal' => 'required|date',
            'foto_rapor' => 'sometimes|array',
            'foto_rapor.*' => 'image|mimes:jpeg,png,jpg|max:2048'
        ]);
        
        // Create raport
        $raport = new Raport();
        $raport->id_anak = $childId;
        $raport->tingkat = $validatedData['tingkat'];
        $raport->kelas = $validatedData['kelas'];
        $raport->nilai_max = $validatedData['nilai_max'] ?? null;
        $raport->nilai_min = $validatedData['nilai_min'] ?? null;
        $raport->nilai_rata_rata = $validatedData['nilai_rata_rata'] ?? null;
        $raport->semester = $validatedData['semester'];
        $raport->tanggal = $validatedData['tanggal'];
        $raport->is_read = 'tidak';
        $raport->save();
        
        // Handle foto rapor upload
        if ($request->hasFile('foto_rapor')) {
            foreach ($request->file('foto_rapor') as $foto) {
                $filename = Str::uuid() . '.' . $foto->getClientOriginalExtension();
                $path = $foto->storeAs("Raport/{$raport->id_raport}", $filename, 'public');
                
                FotoRapor::create([
                    'id_rapor' => $raport->id_raport,
                    'nama' => $filename
                ]);
            }
        }
        
        // Get updated raport with relations
        $updatedRaport = Raport::with(['anak', 'fotoRapor'])->find($raport->id_raport);
        
        return response()->json([
            'success' => true,
            'message' => 'Raport berhasil dibuat',
            'data' => new RaportCollection($updatedRaport)
        ], 201);
    }
    
    /**
     * Update the specified raport.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $childId
     * @param  int  $raportId
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $childId, $raportId)
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
        
        // Find the child and ensure it belongs to the shelter
        $anak = Anak::where('id_anak', $childId)
                    ->where('id_shelter', $user->adminShelter->id_shelter)
                    ->firstOrFail();
        
        // Find raport by ID and ensure it belongs to the child
        $raport = Raport::where('id_raport', $raportId)
                        ->where('id_anak', $childId)
                        ->firstOrFail();
        
        // Validation rules
        $validatedData = $request->validate([
            'tingkat' => 'sometimes|string|max:50',
            'kelas' => 'sometimes|string|max:50',
            'nilai_max' => 'nullable|numeric',
            'nilai_min' => 'nullable|numeric',
            'nilai_rata_rata' => 'nullable|numeric',
            'semester' => 'sometimes|string|max:20',
            'tanggal' => 'sometimes|date',
            'foto_rapor' => 'sometimes|array',
            'foto_rapor.*' => 'image|mimes:jpeg,png,jpg|max:2048',
            'hapus_foto' => 'sometimes|array' // Optional array of foto rapor IDs to delete
        ]);
        
        // Update raport data
        if (isset($validatedData['tingkat'])) $raport->tingkat = $validatedData['tingkat'];
        if (isset($validatedData['kelas'])) $raport->kelas = $validatedData['kelas'];
        if (isset($validatedData['nilai_max'])) $raport->nilai_max = $validatedData['nilai_max'];
        if (isset($validatedData['nilai_min'])) $raport->nilai_min = $validatedData['nilai_min'];
        if (isset($validatedData['nilai_rata_rata'])) $raport->nilai_rata_rata = $validatedData['nilai_rata_rata'];
        if (isset($validatedData['semester'])) $raport->semester = $validatedData['semester'];
        if (isset($validatedData['tanggal'])) $raport->tanggal = $validatedData['tanggal'];
        $raport->save();
        
        // Handle hapus foto
        if ($request->has('hapus_foto')) {
            foreach ($validatedData['hapus_foto'] as $fotoId) {
                $fotoRapor = FotoRapor::where('id_foto_rapor', $fotoId)
                                      ->where('id_rapor', $raportId)
                                      ->first();
                                      
                if ($fotoRapor) {
                    // Hapus file dari storage
                    if (Storage::disk('public')->exists("Raport/{$raport->id_raport}/{$fotoRapor->nama}")) {
                        Storage::disk('public')->delete("Raport/{$raport->id_raport}/{$fotoRapor->nama}");
                    }
                    // Hapus record dari database
                    $fotoRapor->delete();
                }
            }
        }
        
        // Handle tambah foto rapor
        if ($request->hasFile('foto_rapor')) {
            foreach ($request->file('foto_rapor') as $foto) {
                $filename = Str::uuid() . '.' . $foto->getClientOriginalExtension();
                $path = $foto->storeAs("Raport/{$raport->id_raport}", $filename, 'public');
                
                FotoRapor::create([
                    'id_rapor' => $raport->id_raport,
                    'nama' => $filename
                ]);
            }
        }
        
        // Get updated raport with relations
        $updatedRaport = Raport::with(['anak', 'fotoRapor'])->find($raport->id_raport);
        
        return response()->json([
            'success' => true,
            'message' => 'Raport berhasil diperbarui',
            'data' => new RaportCollection($updatedRaport)
        ], 200);
    }
    
    /**
     * Remove the specified raport.
     *
     * @param  int  $childId
     * @param  int  $raportId
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($childId, $raportId)
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
        
        // Find the child and ensure it belongs to the shelter
        $anak = Anak::where('id_anak', $childId)
                    ->where('id_shelter', $user->adminShelter->id_shelter)
                    ->firstOrFail();
        
        // Find raport by ID and ensure it belongs to the child
        $raport = Raport::where('id_raport', $raportId)
                        ->where('id_anak', $childId)
                        ->firstOrFail();
        
        // Hapus foto-foto terkait
        $fotoRaports = FotoRapor::where('id_rapor', $raportId)->get();
        foreach ($fotoRaports as $foto) {
            // Hapus file dari storage
            if (Storage::disk('public')->exists("Raport/{$raport->id_raport}/{$foto->nama}")) {
                Storage::disk('public')->delete("Raport/{$raport->id_raport}/{$foto->nama}");
            }
            // Hapus record dari database
            $foto->delete();
        }
        
        // Hapus raport
        $raport->delete();
        
        return response()->json([
            'success' => true,
            'message' => 'Raport berhasil dihapus'
        ], 200);
    }
}