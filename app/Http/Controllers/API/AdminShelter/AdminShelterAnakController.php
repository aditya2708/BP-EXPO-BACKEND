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
    public function index(Request $request)
    {
        $user = Auth::user();
        
        if (!$user->adminShelter) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        $query = Anak::where('id_shelter', $user->adminShelter->id_shelter)
                     ->withActiveFamily();

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                  ->orWhere('nick_name', 'like', "%{$search}%")
                  ->orWhere('nik_anak', 'like', "%{$search}%");
            });
        }

        if ($request->has('status')) {
            $query->where('status_validasi', $request->status);
        }

        if ($request->has('id_level_anak_binaan')) {
            if ($request->id_level_anak_binaan === 'null') {
                $query->whereNull('id_level_anak_binaan');
            } else {
                $query->where('id_level_anak_binaan', $request->id_level_anak_binaan);
            }
        }

        if ($request->has('id_kelompok')) {
            if ($request->id_kelompok === 'null') {
                $query->whereNull('id_kelompok');
            } else {
                $query->where('id_kelompok', $request->id_kelompok);
            }
        }

        if ($request->has('jenis_anak_binaan')) {
            $query->where('jenis_anak_binaan', $request->jenis_anak_binaan);
        }

        if ($request->has('hafalan')) {
            $query->where('hafalan', $request->hafalan);
        }

        $perPage = $request->per_page ?? 10;

        $query->with(['kelompok', 'shelter', 'anakPendidikan', 'levelAnakBinaan', 'keluarga']);

        $anak = $query->latest()->paginate($perPage);

        $summary = [
            'total' => Anak::where('id_shelter', $user->adminShelter->id_shelter)
                          ->withActiveFamily()
                          ->count(),
            'anak_aktif' => Anak::where('id_shelter', $user->adminShelter->id_shelter)
                            ->withActiveFamily()
                            ->where('status_validasi', 'aktif')
                            ->count(),
            'anak_tidak_aktif' => Anak::where('id_shelter', $user->adminShelter->id_shelter)
                                ->withActiveFamily()
                                ->where('status_validasi', 'non-aktif')
                                ->count(),
            'dengan_kelompok' => Anak::where('id_shelter', $user->adminShelter->id_shelter)
                                ->withActiveFamily()
                                ->whereNotNull('id_kelompok')
                                ->count(),
            'tanpa_kelompok' => Anak::where('id_shelter', $user->adminShelter->id_shelter)
                                ->withActiveFamily()
                                ->whereNull('id_kelompok')
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

    public function show($id)
    {
        $user = Auth::user();
        
        if (!$user->adminShelter) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        $anak = Anak::where('id_shelter', $user->adminShelter->id_shelter)
                    ->withActiveFamily()
                    ->with([
                        'keluarga', 
                        'kelompok', 
                        'shelter', 
                        'anakPendidikan',
                        'levelAnakBinaan'
                    ])
                    ->findOrFail($id);

        return response()->json([
            'success' => true,
            'message' => 'Detail Anak',
            'data' => new AnakCollection($anak)
        ], 200);
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        
        if (!$user->adminShelter) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        $validatedData = $request->validate([
            'id_keluarga' => 'required|exists:keluarga,id_keluarga',
            'id_anak_pend' => 'nullable|exists:anak_pendidikan,id_anak_pend',
            'id_kelompok' => 'nullable|exists:kelompok,id_kelompok',
            'nik_anak' => 'nullable|unique:anak,nik_anak|max:16',
            'anak_ke' => 'nullable|integer',
            'dari_bersaudara' => 'nullable|integer',
            'nick_name' => 'required|string|max:255',
            'full_name' => 'required|string|max:255',
            'agama' => 'required|in:Islam,Kristen,Katolik,Buddha,Hindu,Konghucu',
            'tempat_lahir' => 'required|string|max:255',
            'tanggal_lahir' => 'required|date',
            'jenis_kelamin' => 'sometimes|in:Laki-laki,Perempuan',
            'tinggal_bersama' => 'required|in:Ayah,Ibu,Ayah dan Ibu,Wali',
            'jenis_anak_binaan' => 'required|in:BPCB,NPB',
            'hafalan' => 'required|in:Tahfidz,Non-Tahfidz',
            'status_validasi' => 'nullable|in:aktif,non-aktif,Ditolak,Ditangguhkan',
            'foto' => 'nullable|image|max:2048',
            'background_story' => 'nullable|string',
            'educational_goals' => 'nullable|string',
            'personality_traits' => 'nullable|string',
            'special_needs' => 'nullable|string',
            'marketplace_featured' => 'nullable|boolean',
        ]);

        $validatedData['id_shelter'] = $user->adminShelter->id_shelter;

        if (isset($validatedData['personality_traits'])) {
            $validatedData['personality_traits'] = $validatedData['personality_traits'] 
                ? explode(',', $validatedData['personality_traits']) 
                : null;
        }

        if (isset($validatedData['id_kelompok'])) {
            $kelompok = \App\Models\Kelompok::findOrFail($validatedData['id_kelompok']);
            $validatedData['id_level_anak_binaan'] = $kelompok->id_level_anak_binaan;
        }

        $anak = new Anak($validatedData);

        $anak->status_validasi = $validatedData['status_validasi'] ?? 'non-aktif';

        $anak->status_cpb = Anak::STATUS_CPB_BCPB;

        $anak->save();

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

    public function update(Request $request, $id)
    {
        $user = Auth::user();
        
        if (!$user->adminShelter) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        $anak = Anak::where('id_shelter', $user->adminShelter->id_shelter)
                    ->findOrFail($id);

        $validatedData = $request->validate([
            'id_keluarga' => 'sometimes|exists:keluarga,id_keluarga',
            'id_anak_pend' => 'nullable|exists:anak_pendidikan,id_anak_pend',
            'id_kelompok' => 'nullable|exists:kelompok,id_kelompok',
            'nik_anak' => 'nullable|unique:anak,nik_anak,' . $anak->id_anak . ',id_anak|max:16',
            'anak_ke' => 'nullable|integer',
            'dari_bersaudara' => 'nullable|integer',
            'nick_name' => 'sometimes|string|max:255',
            'full_name' => 'sometimes|string|max:255',
            'agama' => 'sometimes|in:Islam,Kristen,Katolik,Buddha,Hindu,Konghucu',
            'tempat_lahir' => 'sometimes|string|max:255',
            'tanggal_lahir' => 'sometimes|date',
            'jenis_kelamin' => 'sometimes|in:Laki-laki,Perempuan',
            'tinggal_bersama' => 'sometimes|in:Ayah,Ibu,Ayah dan Ibu,Wali',
            'jenis_anak_binaan' => 'sometimes|in:BPCB,NPB',
            'hafalan' => 'sometimes|in:Tahfidz,Non-Tahfidz',
            'status_validasi' => 'sometimes|in:aktif,non-aktif,Ditolak,Ditangguhkan',
            'foto' => 'nullable|image|max:2048',
            'background_story' => 'nullable|string',
            'educational_goals' => 'nullable|string',
            'personality_traits' => 'nullable|string',
            'special_needs' => 'nullable|string',
            'marketplace_featured' => 'nullable|boolean',
        ]);

        if (isset($validatedData['personality_traits'])) {
            $validatedData['personality_traits'] = $validatedData['personality_traits'] 
                ? explode(',', $validatedData['personality_traits']) 
                : null;
        }

        if (isset($validatedData['id_kelompok'])) {
            if ($validatedData['id_kelompok'] === null) {
                $validatedData['id_level_anak_binaan'] = null;
            } else {
                $kelompok = \App\Models\Kelompok::findOrFail($validatedData['id_kelompok']);
                $validatedData['id_level_anak_binaan'] = $kelompok->id_level_anak_binaan;
            }
        }

        $anak->fill($validatedData);

        if (isset($validatedData['jenis_anak_binaan'])) {
            $anak->status_cpb = $validatedData['jenis_anak_binaan'] === 'BPCB' 
                ? Anak::STATUS_CPB_BCPB 
                : Anak::STATUS_CPB_NPB;
        }

        if ($request->hasFile('foto')) {
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
    
    public function toggleStatus($id)
    {
        $user = Auth::user();
        
        if (!$user->adminShelter) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        $anak = Anak::where('id_shelter', $user->adminShelter->id_shelter)
                    ->findOrFail($id);

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

    public function destroy($id)
    {
        $user = Auth::user();
        
        if (!$user->adminShelter) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        $anak = Anak::where('id_shelter', $user->adminShelter->id_shelter)
                    ->findOrFail($id);

        if ($anak->id_kelompok) {
            $kelompok = \App\Models\Kelompok::find($anak->id_kelompok);
            if ($kelompok) {
                $kelompok->jumlah_anggota = $kelompok->anak()->where('id_anak', '!=', $id)->count();
                $kelompok->save();
            }
        }

        if ($anak->foto) {
            Storage::disk('public')->delete("Anak/{$anak->id_anak}/{$anak->foto}");
        }

        $anak->delete();

        return response()->json([
            'success' => true,
            'message' => 'Anak berhasil dihapus'
        ], 200);
    }
}