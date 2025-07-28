<?php

namespace App\Http\Controllers\API\AdminCabang;

use App\Http\Controllers\Controller;
use App\Models\{Donatur, User, Wilbin, Shelter, Bank};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Hash, Storage, Validator};

class AdminCabangDonaturController extends Controller
{
    private function getAdminCabang()
    {
        return auth()->user()->adminCabang;
    }

    private function validateDonaturAccess($donaturId)
    {
        return Donatur::where('id_kacab', $this->getAdminCabang()->id_kacab)
            ->findOrFail($donaturId);
    }

    public function index(Request $request)
    {
        $query = Donatur::with(['user', 'wilbin', 'shelter', 'bank', 'anak'])
            ->where('id_kacab', $this->getAdminCabang()->id_kacab);

        // Apply filters
        $this->applyFilters($query, $request);

        // Sorting & Pagination
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $perPage = $request->get('per_page', 15);
        
        $donatur = $query->orderBy($sortBy, $sortOrder)->paginate($perPage);

        return response()->json([
            'message' => 'Donatur list retrieved successfully',
            'data' => $donatur
        ]);
    }

    public function show($id)
    {
        $donatur = $this->validateDonaturAccess($id);
        $donatur->load(['user', 'wilbin', 'shelter', 'bank', 'anak.shelter']);

        return response()->json([
            'message' => 'Donatur detail retrieved successfully',
            'data' => $donatur
        ]);
    }

    public function store(Request $request)
    {
        $validator = $this->validateDonaturData($request);
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $this->validateWilbinShelter($request);

        DB::beginTransaction();
        try {
            $user = $this->createUser($request);
            $donatur = $this->createDonatur($request, $user);
            $this->handlePhotoUpload($request, $donatur);

            $donatur->load(['user', 'wilbin', 'shelter', 'bank']);
            DB::commit();

            return response()->json([
                'message' => 'Donatur created successfully',
                'data' => $donatur
            ], 201);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'message' => 'Failed to create donatur',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $donatur = $this->validateDonaturAccess($id);
        
        $validator = $this->validateDonaturData($request, $donatur->id_users);
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $this->validateWilbinShelter($request);

        DB::beginTransaction();
        try {
            $this->updateUser($request, $donatur);
            $this->updateDonatur($request, $donatur);
            $this->handlePhotoUpload($request, $donatur);

            $donatur->load(['user', 'wilbin', 'shelter', 'bank']);
            DB::commit();

            return response()->json([
                'message' => 'Donatur updated successfully',
                'data' => $donatur
            ]);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'message' => 'Failed to update donatur',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        $donatur = $this->validateDonaturAccess($id);

        if ($donatur->anak()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete donatur. Donatur has associated children.'
            ], 400);
        }

        DB::beginTransaction();
        try {
            $this->deletePhoto($donatur);
            $donatur->user->delete();
            $donatur->delete();
            DB::commit();

            return response()->json([
                'message' => 'Donatur deleted successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'message' => 'Failed to delete donatur',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getStats()
    {
        $kacabId = $this->getAdminCabang()->id_kacab;
        
        $stats = [
            'total_donatur' => Donatur::where('id_kacab', $kacabId)->count(),
            'donatur_with_children' => Donatur::where('id_kacab', $kacabId)->whereHas('anak')->count(),
            'donatur_without_children' => Donatur::where('id_kacab', $kacabId)->whereDoesntHave('anak')->count(),
            'by_diperuntukan' => Donatur::where('id_kacab', $kacabId)
                ->selectRaw('diperuntukan, COUNT(*) as count')
                ->groupBy('diperuntukan')->pluck('count', 'diperuntukan'),
            'by_wilbin' => $this->getWilbinStats($kacabId)
        ];

        return response()->json([
            'message' => 'Donatur statistics retrieved successfully',
            'data' => $stats
        ]);
    }

    public function getDropdownData()
    {
        $kacabId = $this->getAdminCabang()->id_kacab;

        return response()->json([
            'message' => 'Dropdown data retrieved successfully',
            'data' => [
                'wilbins' => Wilbin::where('id_kacab', $kacabId)->select('id_wilbin', 'nama_wilbin')->get(),
                'shelters' => Shelter::whereHas('wilbin', fn($q) => $q->where('id_kacab', $kacabId))
                    ->with('wilbin:id_wilbin,nama_wilbin')
                    ->select('id_shelter', 'nama_shelter', 'id_wilbin')->get(),
                'banks' => Bank::select('id_bank', 'nama_bank')->get(),
                'diperuntukan_options' => [
                    ['value' => 'anak', 'label' => 'Anak'],
                    ['value' => 'shelter', 'label' => 'Shelter'],
                    ['value' => 'kacab', 'label' => 'Cabang']
                ]
            ]
        ]);
    }

    // Private helper methods
    private function applyFilters($query, $request)
    {
        if ($request->filled('id_wilbin')) $query->where('id_wilbin', $request->id_wilbin);
        if ($request->filled('id_shelter')) $query->where('id_shelter', $request->id_shelter);
        if ($request->filled('diperuntukan')) $query->where('diperuntukan', $request->diperuntukan);
        
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(fn($q) => $q->where('nama_lengkap', 'like', "%{$search}%")
                ->orWhere('no_hp', 'like', "%{$search}%")
                ->orWhere('alamat', 'like', "%{$search}%"));
        }
    }

    private function validateDonaturData($request, $userId = null)
    {
        return Validator::make($request->all(), [
            'email' => 'required|email|unique:users,email' . ($userId ? ",{$userId},id_users" : ''),
            'password' => ($userId ? 'nullable' : 'required') . '|min:6',
            'nama_lengkap' => 'required|string|max:255',
            'alamat' => 'required|string',
            'no_hp' => 'required|string|max:20',
            'id_wilbin' => 'required|exists:wilbin,id_wilbin',
            'id_shelter' => 'required|exists:shelter,id_shelter',
            'id_bank' => 'nullable|exists:bank,id_bank',
            'no_rekening' => 'nullable|string|max:50',
            'diperuntukan' => 'required|in:anak,shelter,kacab',
            'foto' => 'nullable|image|mimes:jpeg,png,jpg|max:2048'
        ]);
    }

    private function validateWilbinShelter($request)
    {
        $kacabId = $this->getAdminCabang()->id_kacab;
        
        if (!Wilbin::where('id_wilbin', $request->id_wilbin)->where('id_kacab', $kacabId)->exists()) {
            abort(404, 'Wilbin tidak ditemukan atau tidak dalam cabang Anda');
        }

        if (!Shelter::where('id_shelter', $request->id_shelter)->where('id_wilbin', $request->id_wilbin)->exists()) {
            abort(404, 'Shelter tidak ditemukan atau tidak dalam wilbin yang dipilih');
        }
    }

    private function createUser($request)
    {
        return User::create([
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'donatur',
            'is_active' => true
        ]);
    }

    private function createDonatur($request, $user)
    {
        return Donatur::create([
            'id_users' => $user->id_users,
            'id_kacab' => $this->getAdminCabang()->id_kacab,
            'id_wilbin' => $request->id_wilbin,
            'id_shelter' => $request->id_shelter,
            'nama_lengkap' => $request->nama_lengkap,
            'alamat' => $request->alamat,
            'no_hp' => $request->no_hp,
            'id_bank' => $request->id_bank,
            'no_rekening' => $request->no_rekening,
            'diperuntukan' => $request->diperuntukan
        ]);
    }

    private function updateUser($request, $donatur)
    {
        $userData = ['email' => $request->email];
        if ($request->filled('password')) {
            $userData['password'] = Hash::make($request->password);
        }
        $donatur->user->update($userData);
    }

    private function updateDonatur($request, $donatur)
    {
        $donatur->update([
            'nama_lengkap' => $request->nama_lengkap,
            'alamat' => $request->alamat,
            'no_hp' => $request->no_hp,
            'id_wilbin' => $request->id_wilbin,
            'id_shelter' => $request->id_shelter,
            'id_bank' => $request->id_bank,
            'no_rekening' => $request->no_rekening,
            'diperuntukan' => $request->diperuntukan
        ]);
    }

    private function handlePhotoUpload($request, $donatur)
    {
        if (!$request->hasFile('foto')) return;

        // Delete old photo
        if ($donatur->foto) {
            Storage::delete("public/Donatur/{$donatur->id_donatur}/{$donatur->foto}");
        }

        $file = $request->file('foto');
        $fileName = time() . '_' . $file->getClientOriginalName();
        $file->storeAs("public/Donatur/{$donatur->id_donatur}", $fileName);
        $donatur->update(['foto' => $fileName]);
    }

    private function deletePhoto($donatur)
    {
        if ($donatur->foto) {
            Storage::delete("public/Donatur/{$donatur->id_donatur}/{$donatur->foto}");
        }
    }

    private function getWilbinStats($kacabId)
    {
        return Donatur::where('id_kacab', $kacabId)
            ->with('wilbin:id_wilbin,nama_wilbin')
            ->selectRaw('id_wilbin, COUNT(*) as count')
            ->groupBy('id_wilbin')->get()
            ->map(fn($item) => [
                'wilbin_name' => $item->wilbin->nama_wilbin ?? 'Unknown',
                'count' => $item->count
            ]);
    }
}