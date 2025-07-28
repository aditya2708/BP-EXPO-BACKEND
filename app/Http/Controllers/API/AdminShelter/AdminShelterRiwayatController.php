<?php

namespace App\Http\Controllers\API\AdminShelter;

use App\Http\Controllers\Controller;
use App\Models\Histori;
use App\Models\Anak;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AdminShelterRiwayatController extends Controller
{
    public function index(Request $request, $anakId)
    {
        $user = Auth::user();
        
        if (!$user->adminShelter) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        $anak = Anak::where('id_shelter', $user->adminShelter->id_shelter)
                    ->where('id_anak', $anakId)
                    ->first();

        if (!$anak) {
            return response()->json([
                'success' => false,
                'message' => 'Anak not found'
            ], 404);
        }

        $query = Histori::where('id_anak', $anakId);

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('jenis_histori', 'like', "%{$search}%")
                  ->orWhere('nama_histori', 'like', "%{$search}%");
            });
        }

        $perPage = $request->per_page ?? 10;
        $riwayat = $query->latest('tanggal')->paginate($perPage);

        foreach ($riwayat as $item) {
            if ($item->foto) {
                $item->foto_url = url("storage/Histori/{$anakId}/{$item->foto}");
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Daftar Riwayat',
            'data' => $riwayat->items(),
            'pagination' => [
                'total' => $riwayat->total(),
                'per_page' => $riwayat->perPage(),
                'current_page' => $riwayat->currentPage(),
                'last_page' => $riwayat->lastPage(),
                'from' => $riwayat->firstItem(),
                'to' => $riwayat->lastItem()
            ]
        ], 200);
    }

    public function show($anakId, $riwayatId)
    {
        $user = Auth::user();
        
        if (!$user->adminShelter) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        $anak = Anak::where('id_shelter', $user->adminShelter->id_shelter)
                    ->where('id_anak', $anakId)
                    ->first();

        if (!$anak) {
            return response()->json([
                'success' => false,
                'message' => 'Anak not found'
            ], 404);
        }

        $riwayat = Histori::where('id_anak', $anakId)
                          ->where('id_histori', $riwayatId)
                          ->with('anak')
                          ->first();

        if (!$riwayat) {
            return response()->json([
                'success' => false,
                'message' => 'Riwayat not found'
            ], 404);
        }

        if ($riwayat->foto) {
            $riwayat->foto_url = url("storage/Histori/{$anakId}/{$riwayat->foto}");
        }

        return response()->json([
            'success' => true,
            'message' => 'Detail Riwayat',
            'data' => $riwayat
        ], 200);
    }

    public function store(Request $request, $anakId)
    {
        $user = Auth::user();
        
        if (!$user->adminShelter) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        $anak = Anak::where('id_shelter', $user->adminShelter->id_shelter)
                    ->where('id_anak', $anakId)
                    ->first();

        if (!$anak) {
            return response()->json([
                'success' => false,
                'message' => 'Anak not found'
            ], 404);
        }

        $validatedData = $request->validate([
            'jenis_histori' => 'required|string|max:255',
            'nama_histori' => 'required|string|max:255',
            'di_opname' => 'required|in:YA,TIDAK',
            'dirawat_id' => 'required_if:di_opname,YA|nullable|string|max:255',
            'tanggal' => 'required|date',
            'foto' => 'nullable|image|max:2048'
        ]);

        $validatedData['id_anak'] = $anakId;
        $validatedData['is_read'] = "Tidak";
        
        if ($validatedData['di_opname'] === 'TIDAK') {
            $validatedData['dirawat_id'] = null;
        }

        $riwayat = new Histori($validatedData);
        $riwayat->save();

        if ($request->hasFile('foto')) {
            $file = $request->file('foto');
            $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs("Histori/{$anakId}", $filename, 'public');
            $riwayat->foto = $filename;
            $riwayat->save();
        }

        $riwayat->load('anak');

        if ($riwayat->foto) {
            $riwayat->foto_url = url("storage/Histori/{$anakId}/{$riwayat->foto}");
        }

        return response()->json([
            'success' => true,
            'message' => 'Riwayat berhasil ditambahkan',
            'data' => $riwayat
        ], 201);
    }

    public function update(Request $request, $anakId, $riwayatId)
    {
        $user = Auth::user();
        
        if (!$user->adminShelter) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        $anak = Anak::where('id_shelter', $user->adminShelter->id_shelter)
                    ->where('id_anak', $anakId)
                    ->first();

        if (!$anak) {
            return response()->json([
                'success' => false,
                'message' => 'Anak not found'
            ], 404);
        }

        $riwayat = Histori::where('id_anak', $anakId)
                          ->where('id_histori', $riwayatId)
                          ->first();

        if (!$riwayat) {
            return response()->json([
                'success' => false,
                'message' => 'Riwayat not found'
            ], 404);
        }

        $validatedData = $request->validate([
            'jenis_histori' => 'sometimes|string|max:255',
            'nama_histori' => 'sometimes|string|max:255',
            'di_opname' => 'sometimes|in:YA,TIDAK',
            'dirawat_id' => 'required_if:di_opname,YA|nullable|string|max:255',
            'tanggal' => 'sometimes|date',
            'foto' => 'nullable|image|max:2048'
        ]);
        
        if (isset($validatedData['di_opname']) && $validatedData['di_opname'] === 'TIDAK') {
            $validatedData['dirawat_id'] = null;
        }

        $riwayat->fill($validatedData);

        if ($request->hasFile('foto')) {
            if ($riwayat->foto) {
                Storage::disk('public')->delete("Histori/{$anakId}/{$riwayat->foto}");
            }

            $file = $request->file('foto');
            $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs("Histori/{$anakId}", $filename, 'public');
            $riwayat->foto = $filename;
        }

        $riwayat->save();
        $riwayat->load('anak');

        if ($riwayat->foto) {
            $riwayat->foto_url = url("storage/Histori/{$anakId}/{$riwayat->foto}");
        }

        return response()->json([
            'success' => true,
            'message' => 'Riwayat berhasil diperbarui',
            'data' => $riwayat
        ], 200);
    }

    public function destroy($anakId, $riwayatId)
    {
        $user = Auth::user();
        
        if (!$user->adminShelter) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        $anak = Anak::where('id_shelter', $user->adminShelter->id_shelter)
                    ->where('id_anak', $anakId)
                    ->first();

        if (!$anak) {
            return response()->json([
                'success' => false,
                'message' => 'Anak not found'
            ], 404);
        }

        $riwayat = Histori::where('id_anak', $anakId)
                          ->where('id_histori', $riwayatId)
                          ->first();

        if (!$riwayat) {
            return response()->json([
                'success' => false,
                'message' => 'Riwayat not found'
            ], 404);
        }

        if ($riwayat->foto) {
            Storage::disk('public')->delete("Histori/{$anakId}/{$riwayat->foto}");
        }

        $riwayat->delete();

        return response()->json([
            'success' => true,
            'message' => 'Riwayat berhasil dihapus'
        ], 200);
    }
}