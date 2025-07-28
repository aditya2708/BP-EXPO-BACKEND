<?php

namespace App\Http\Controllers\API\AdminShelter;

use App\Http\Controllers\Controller;
use App\Models\Tutor;
use App\Models\Kacab;
use App\Models\Wilbin;
use App\Models\Shelter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AdminShelterTutorController extends Controller
{
    /**
     * Display a listing of tutors.
     *
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

        $query = Tutor::where('id_shelter', $user->adminShelter->shelter->id_shelter);

        // Search filter
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                  ->orWhere('maple', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Default pagination
        $perPage = $request->per_page ?? 10;

        // Load relationships
        $query->with(['kacab', 'wilbin', 'shelter']);

        // Paginate
        $tutors = $query->latest()->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Daftar Tutor',
            'data' => $tutors->items(),
            'pagination' => [
                'total' => $tutors->total(),
                'per_page' => $tutors->perPage(),
                'current_page' => $tutors->currentPage(),
                'last_page' => $tutors->lastPage(),
                'from' => $tutors->firstItem(),
                'to' => $tutors->lastItem()
            ],
        ], 200);
    }

    /**
     * Display the specified tutor.
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

        // Find tutor by ID and ensure it belongs to the shelter
        $tutor = Tutor::where('id_shelter', $user->adminShelter->shelter->id_shelter)
                    ->with(['kacab', 'wilbin', 'shelter'])
                    ->findOrFail($id);

        return response()->json([
            'success' => true,
            'message' => 'Detail Tutor',
            'data' => $tutor
        ], 200);
    }

    /**
     * Store a newly created tutor.
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
            'nama' => 'required|string|max:255',
            'pendidikan' => 'required|string|max:255',
            'alamat' => 'required|string|max:255',
            'email' => 'required|email|unique:tutor,email',
            'no_hp' => 'required|string|max:15',
            'maple' => 'required|string|max:255',
            'jenis_tutor' => 'required|in:tahfidz,non_tahfidz',
            'foto' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        // Add shelter ID, wilbin ID, and kacab ID from authenticated user
        $validatedData['id_shelter'] = $user->adminShelter->shelter->id_shelter;
        $validatedData['id_wilbin'] = $user->adminShelter->shelter->id_wilbin;
        $validatedData['id_kacab'] = $user->adminShelter->shelter->wilbin->id_kacab;

        // Create Tutor record
        $tutor = Tutor::create($validatedData);

        // Handle foto upload
        if ($request->hasFile('foto')) {
            $folderPath = "Tutor/{$tutor->id_tutor}";
            $fileName = $request->file('foto')->getClientOriginalName();
            $path = $request->file('foto')->storeAs($folderPath, $fileName, 'public');
            $tutor->update(['foto' => $fileName]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Tutor berhasil ditambahkan',
            'data' => $tutor->load(['kacab', 'wilbin', 'shelter'])
        ], 201);
    }

    /**
     * Update the specified tutor.
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

        // Find tutor by ID and ensure it belongs to the shelter
        $tutor = Tutor::where('id_shelter', $user->adminShelter->shelter->id_shelter)
                    ->findOrFail($id);

        // Validation rules
        $validatedData = $request->validate([
            'nama' => 'sometimes|required|string|max:255',
            'pendidikan' => 'sometimes|required|string|max:255',
            'alamat' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|email|unique:tutor,email,' . $id . ',id_tutor',
            'no_hp' => 'sometimes|required|string|max:15',
            'maple' => 'sometimes|required|string|max:255',
            'jenis_tutor' => 'sometimes|required|in:tahfidz,non_tahfidz',
            'foto' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        // Important: Only update the fields that were sent in the request
        // This prevents the photo field from being set to null when not included
        
        // Handle foto upload
        if ($request->hasFile('foto')) {
            // Delete old foto if exists
            if ($tutor->foto) {
                Storage::disk('public')->delete("Tutor/{$tutor->id_tutor}/{$tutor->foto}");
            }

            $folderPath = "Tutor/{$tutor->id_tutor}";
            $fileName = $request->file('foto')->getClientOriginalName();
            $path = $request->file('foto')->storeAs($folderPath, $fileName, 'public');
            $validatedData['foto'] = $fileName;
        } else {
            // Remove foto from validated data if not present in request
            // to avoid updating it to null
            if (isset($validatedData['foto'])) {
                unset($validatedData['foto']);
            }
        }

        // Update only the fields that were validated
        $tutor->update($validatedData);

        return response()->json([
            'success' => true,
            'message' => 'Tutor berhasil diperbarui',
            'data' => $tutor->fresh()->load(['kacab', 'wilbin', 'shelter'])
        ], 200);
    }

    /**
     * Remove the specified tutor.
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

        // Find tutor by ID and ensure it belongs to the shelter
        $tutor = Tutor::where('id_shelter', $user->adminShelter->shelter->id_shelter)
                    ->findOrFail($id);

        // Delete associated foto if exists
        if ($tutor->foto) {
            Storage::disk('public')->delete("Tutor/{$tutor->id_tutor}/{$tutor->foto}");
        }

        // Delete the tutor record
        $tutor->delete();

        return response()->json([
            'success' => true,
            'message' => 'Tutor berhasil dihapus'
        ], 200);
    }
}