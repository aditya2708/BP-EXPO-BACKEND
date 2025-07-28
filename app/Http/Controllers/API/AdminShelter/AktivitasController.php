<?php

namespace App\Http\Controllers\API\AdminShelter;

use App\Http\Controllers\Controller;
use App\Models\Aktivitas;
use App\Models\Shelter;
use App\Models\Materi;
use App\Models\Tutor;
use App\Http\Resources\AktivitasResource;
use App\Http\Requests\AktivitasRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class AktivitasController extends Controller
{
    /**
     * Display a listing of activities for the shelter.
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
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

    // Get shelter_id from the admin_shelter relationship
    $shelterId = $user->adminShelter->shelter->id_shelter;
    
    // Base query with materiData and tutor relations
    $query = Aktivitas::where('id_shelter', $shelterId)->with(['materiData', 'tutor']);
    
    // Query params
    $search = $request->input('search');
    $dateFrom = $request->input('date_from');
    $dateTo = $request->input('date_to');
    $type = $request->input('jenis_kegiatan');
    $typeNot = $request->input('jenis_kegiatan_not');
    $namaKelompok = $request->input('nama_kelompok');
    $tutorId = $request->input('id_tutor');
    
    // Apply filters
    if ($search) {
        $query->where(function($q) use ($search) {
            $q->where('jenis_kegiatan', 'like', "%$search%")
              ->orWhere('materi', 'like', "%$search%")
              ->orWhere('nama_kelompok', 'like', "%$search%")
              ->orWhereHas('tutor', function($tq) use ($search) {
                  $tq->where('nama', 'like', "%$search%");
              });
        });
    }
    
    if ($dateFrom) {
        $query->whereDate('tanggal', '>=', $dateFrom);
    }
    
    if ($dateTo) {
        $query->whereDate('tanggal', '<=', $dateTo);
    }
    
    if ($type) {
        $query->where('jenis_kegiatan', $type);
    }
    
    if ($typeNot) {
        $query->where('jenis_kegiatan', '!=', $typeNot);
    }
    
    if ($namaKelompok) {
        $query->where('nama_kelompok', $namaKelompok);
    }
    
    if ($tutorId) {
        $query->where('id_tutor', $tutorId);
    }
    
    // Default pagination
    $perPage = $request->per_page ?? 10;
    
    // Order by date (most recent first)
    $aktivitas = $query->orderBy('tanggal', 'desc')->paginate($perPage);
    
    return response()->json([
        'success' => true,
        'data' => $aktivitas->items(),
        'meta' => [
            'total' => $aktivitas->total(),
            'current_page' => $aktivitas->currentPage(),
            'last_page' => $aktivitas->lastPage(),
            'from' => $aktivitas->firstItem(),
            'to' => $aktivitas->lastItem()
        ]
    ]);
}

    /**
     * Store a newly created activity.
     *
     * @param  AktivitasRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function store(AktivitasRequest $request)
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

    // Get shelter_id from the admin_shelter relationship
    $shelterId = $user->adminShelter->shelter->id_shelter;
    
    // Validate tutor belongs to the same shelter if provided
    if ($request->id_tutor) {
        $tutor = Tutor::find($request->id_tutor);
        if (!$tutor || $tutor->id_shelter != $shelterId) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid tutor selected. Tutor must belong to your shelter.'
            ], 400);
        }
    }
    
    // Create activity
    $aktivitas = new Aktivitas();
    $aktivitas->id_shelter = $shelterId;
    $aktivitas->id_tutor = $request->id_tutor;
    $aktivitas->jenis_kegiatan = $request->jenis_kegiatan;
    
    // Handle level and nama_kelompok based on activity type
    if ($request->jenis_kegiatan === 'Bimbel') {
        $aktivitas->level = $request->level ?? '';
        $aktivitas->nama_kelompok = $request->nama_kelompok ?? '';
        $aktivitas->id_materi = $request->id_materi;

        if ($request->id_materi) {
            $materi = Materi::find($request->id_materi);
            if ($materi) {
                $aktivitas->materi = $materi->mata_pelajaran . ' - ' . $materi->nama_materi;
            }
        } else {
            $aktivitas->materi = $request->materi ?? '';
        }
    } else {
        // For "Kegiatan" type, set empty values
        $aktivitas->level = '';
        $aktivitas->nama_kelompok = '';
        $aktivitas->id_materi = null;
        $aktivitas->materi = $request->materi ?? '';
    }
    
    $aktivitas->tanggal = $request->tanggal;
    
    // Add the missing time-related fields
    $aktivitas->start_time = $request->start_time;
    $aktivitas->end_time = $request->end_time;
    $aktivitas->late_threshold = $request->late_threshold;
    $aktivitas->late_minutes_threshold = $request->late_minutes_threshold ?? 15; // Default to 15 minutes
    
    // Create directory if it doesn't exist
    $aktivitas->save(); // Save first to get id_aktivitas
    
    // Process photos if uploaded
    if ($request->hasFile('foto_1')) {
        $aktivitas->foto_1 = $this->storePhoto($request->file('foto_1'), $aktivitas);
    }
    
    if ($request->hasFile('foto_2')) {
        $aktivitas->foto_2 = $this->storePhoto($request->file('foto_2'), $aktivitas);
    }
    
    if ($request->hasFile('foto_3')) {
        $aktivitas->foto_3 = $this->storePhoto($request->file('foto_3'), $aktivitas);
    }
    
    $aktivitas->save();
    
    // Load relationships for response
    $aktivitas->load(['tutor', 'materiData']);
    
    return response()->json([
        'success' => true,
        'message' => 'Activity created successfully',
        'data' => $aktivitas
    ], 201);
}

    /**
     * Display the specified activity with attendees.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
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

        // Get shelter_id from the admin_shelter relationship
        $shelterId = $user->adminShelter->shelter->id_shelter;
        
        $aktivitas = Aktivitas::with(['absen.absenUser.anak', 'absen.absenUser.tutor', 'materiData', 'tutor'])->findOrFail($id);
        
        // Verify access - only allow access to activities from user's shelter
        if ($aktivitas->id_shelter != $shelterId) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to this activity'
            ], 403);
        }
        
        return response()->json([
            'success' => true,
            'data' => $aktivitas
        ]);
    }

    /**
     * Update the specified activity.
     *
     * @param  AktivitasRequest  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(AktivitasRequest $request, $id)
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

    // Get shelter_id from the admin_shelter relationship
    $shelterId = $user->adminShelter->shelter->id_shelter;
    
    $aktivitas = Aktivitas::findOrFail($id);
    
    // Verify access - only allow access to activities from user's shelter
    if ($aktivitas->id_shelter != $shelterId) {
        return response()->json([
            'success' => false,
            'message' => 'Unauthorized access to this activity'
        ], 403);
    }
    
    // Validate tutor belongs to the same shelter if provided
    if ($request->id_tutor) {
        $tutor = Tutor::find($request->id_tutor);
        if (!$tutor || $tutor->id_shelter != $shelterId) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid tutor selected. Tutor must belong to your shelter.'
            ], 400);
        }
    }
    
    // Update activity data
    $aktivitas->id_tutor = $request->id_tutor;
    $aktivitas->jenis_kegiatan = $request->jenis_kegiatan;
    
    // Handle level and nama_kelompok based on activity type
    if ($request->jenis_kegiatan === 'Bimbel') {
        $aktivitas->level = $request->level ?? '';
        $aktivitas->nama_kelompok = $request->nama_kelompok ?? '';
        $aktivitas->id_materi = $request->id_materi;

        if ($request->id_materi) {
            $materi = Materi::find($request->id_materi);
            if ($materi) {
                $aktivitas->materi = $materi->mata_pelajaran . ' - ' . $materi->nama_materi;
            }
        } else {
            $aktivitas->materi = $request->materi ?? '';
        }
    } else {
        $aktivitas->level = '';
        $aktivitas->nama_kelompok = '';
        $aktivitas->id_materi = null;
        $aktivitas->materi = $request->materi ?? '';
    }
    
    $aktivitas->tanggal = $request->tanggal;
    
    // Update the missing time-related fields
    $aktivitas->start_time = $request->start_time;
    $aktivitas->end_time = $request->end_time;
    $aktivitas->late_threshold = $request->late_threshold;
    $aktivitas->late_minutes_threshold = $request->late_minutes_threshold ?? $aktivitas->late_minutes_threshold ?? 15;
    
    // Process updated photos if uploaded
    if ($request->hasFile('foto_1')) {
        // Delete old photo if exists
        if ($aktivitas->foto_1) {
            Storage::delete("Aktivitas/{$aktivitas->id_aktivitas}/{$aktivitas->foto_1}");
        }
        $aktivitas->foto_1 = $this->storePhoto($request->file('foto_1'), $aktivitas);
    }
    
    if ($request->hasFile('foto_2')) {
        if ($aktivitas->foto_2) {
            Storage::delete("Aktivitas/{$aktivitas->id_aktivitas}/{$aktivitas->foto_2}");
        }
        $aktivitas->foto_2 = $this->storePhoto($request->file('foto_2'), $aktivitas);
    }
    
    if ($request->hasFile('foto_3')) {
        if ($aktivitas->foto_3) {
            Storage::delete("Aktivitas/{$aktivitas->id_aktivitas}/{$aktivitas->foto_3}");
        }
        $aktivitas->foto_3 = $this->storePhoto($request->file('foto_3'), $aktivitas);
    }
    
    $aktivitas->save();
    
    // Load relationships for response
    $aktivitas->load(['tutor', 'materiData']);
    
    return response()->json([
        'success' => true,
        'message' => 'Activity updated successfully',
        'data' => $aktivitas
    ]);
}

    /**
     * Remove the specified activity.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
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

        // Get shelter_id from the admin_shelter relationship
        $shelterId = $user->adminShelter->shelter->id_shelter;
        
        $aktivitas = Aktivitas::findOrFail($id);
        
        // Verify access - only allow access to activities from user's shelter
        if ($aktivitas->id_shelter != $shelterId) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to this activity'
            ], 403);
        }
        
        // Delete associated photos
        if ($aktivitas->foto_1) {
            Storage::delete("Aktivitas/{$aktivitas->id_aktivitas}/{$aktivitas->foto_1}");
        }
        
        if ($aktivitas->foto_2) {
            Storage::delete("Aktivitas/{$aktivitas->id_aktivitas}/{$aktivitas->foto_2}");
        }
        
        if ($aktivitas->foto_3) {
            Storage::delete("Aktivitas/{$aktivitas->id_aktivitas}/{$aktivitas->foto_3}");
        }
        
        // Delete the activity folder
        Storage::deleteDirectory("Aktivitas/{$aktivitas->id_aktivitas}");
        
        // Delete the activity (this should cascade delete related absen records)
        $aktivitas->delete();
        
        return response()->json([
            'success' => true,
            'message' => 'Activity deleted successfully'
        ]);
    }
    
    /**
     * Store a photo for an activity.
     *
     * @param  \Illuminate\Http\UploadedFile  $file
     * @param  Aktivitas  $aktivitas
     * @return string
     */
    private function storePhoto($file, $aktivitas)
    {
        $fileName = time() . '_' . $file->getClientOriginalName();
        $file->storeAs("Aktivitas/{$aktivitas->id_aktivitas}", $fileName);
        return $fileName;
    }
}