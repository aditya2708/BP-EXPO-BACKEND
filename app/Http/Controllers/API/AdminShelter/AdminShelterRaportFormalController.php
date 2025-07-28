<?php

namespace App\Http\Controllers\API\AdminShelter;

use App\Http\Controllers\Controller;
use App\Models\RaportFormal;
use App\Models\Anak;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AdminShelterRaportFormalController extends Controller
{
    public function index($anakId)
    {
        $user = Auth::user();
        
        if (!$user->adminShelter) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        $anak = Anak::where('id_shelter', $user->adminShelter->id_shelter)
                    ->findOrFail($anakId);

        $raportFormal = RaportFormal::where('id_anak', $anakId)
                                   ->with('anak')
                                   ->orderBy('tahun_ajaran', 'desc')
                                   ->orderBy('semester', 'desc')
                                   ->get();

        return response()->json([
            'success' => true,
            'message' => 'Daftar Raport Formal',
            'data' => $raportFormal
        ], 200);
    }

    public function show($anakId, $id)
    {
        $user = Auth::user();
        
        if (!$user->adminShelter) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        $anak = Anak::where('id_shelter', $user->adminShelter->id_shelter)
                    ->findOrFail($anakId);

        $raportFormal = RaportFormal::where('id_anak', $anakId)
                                   ->with('anak')
                                   ->findOrFail($id);

        return response()->json([
            'success' => true,
            'message' => 'Detail Raport Formal',
            'data' => $raportFormal
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
                    ->findOrFail($anakId);

        $validatedData = $request->validate([
            'nama_sekolah' => 'required|string|max:255',
            'tingkat_sekolah' => 'required|in:SD,SMP,SMA,SMK',
            'kelas' => 'required|string|max:10',
            'jurusan' => 'nullable|string|max:255',
            'semester' => 'required|in:ganjil,genap',
            'tahun_ajaran' => 'required|string|max:20',
            'file_raport' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'file_transkrip' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120'
        ]);

        $validatedData['id_anak'] = $anakId;

        $raportFormal = RaportFormal::create($validatedData);

        if ($request->hasFile('file_raport')) {
            $file = $request->file('file_raport');
            $filename = 'raport_' . Str::uuid() . '.' . $file->getClientOriginalExtension();
            $file->storeAs("RaportFormal/{$anakId}", $filename, 'public');
            $raportFormal->file_raport = $filename;
        }

        if ($request->hasFile('file_transkrip')) {
            $file = $request->file('file_transkrip');
            $filename = 'transkrip_' . Str::uuid() . '.' . $file->getClientOriginalExtension();
            $file->storeAs("RaportFormal/{$anakId}", $filename, 'public');
            $raportFormal->file_transkrip = $filename;
        }

        $raportFormal->save();

        return response()->json([
            'success' => true,
            'message' => 'Raport Formal berhasil ditambahkan',
            'data' => $raportFormal
        ], 201);
    }

    public function update(Request $request, $anakId, $id)
    {
        $user = Auth::user();
        
        if (!$user->adminShelter) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        $anak = Anak::where('id_shelter', $user->adminShelter->id_shelter)
                    ->findOrFail($anakId);

        $raportFormal = RaportFormal::where('id_anak', $anakId)->findOrFail($id);

        $validatedData = $request->validate([
            'nama_sekolah' => 'sometimes|string|max:255',
            'tingkat_sekolah' => 'sometimes|in:SD,SMP,SMA,SMK',
            'kelas' => 'sometimes|string|max:10',
            'jurusan' => 'nullable|string|max:255',
            'semester' => 'sometimes|in:ganjil,genap',
            'tahun_ajaran' => 'sometimes|string|max:20',
            'file_raport' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'file_transkrip' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120'
        ]);

        $raportFormal->fill($validatedData);

        if ($request->hasFile('file_raport')) {
            if ($raportFormal->file_raport) {
                Storage::disk('public')->delete("RaportFormal/{$anakId}/{$raportFormal->file_raport}");
            }
            $file = $request->file('file_raport');
            $filename = 'raport_' . Str::uuid() . '.' . $file->getClientOriginalExtension();
            $file->storeAs("RaportFormal/{$anakId}", $filename, 'public');
            $raportFormal->file_raport = $filename;
        }

        if ($request->hasFile('file_transkrip')) {
            if ($raportFormal->file_transkrip) {
                Storage::disk('public')->delete("RaportFormal/{$anakId}/{$raportFormal->file_transkrip}");
            }
            $file = $request->file('file_transkrip');
            $filename = 'transkrip_' . Str::uuid() . '.' . $file->getClientOriginalExtension();
            $file->storeAs("RaportFormal/{$anakId}", $filename, 'public');
            $raportFormal->file_transkrip = $filename;
        }

        $raportFormal->save();

        return response()->json([
            'success' => true,
            'message' => 'Raport Formal berhasil diperbarui',
            'data' => $raportFormal
        ], 200);
    }

    public function destroy($anakId, $id)
    {
        $user = Auth::user();
        
        if (!$user->adminShelter) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        $anak = Anak::where('id_shelter', $user->adminShelter->id_shelter)
                    ->findOrFail($anakId);

        $raportFormal = RaportFormal::where('id_anak', $anakId)->findOrFail($id);

        if ($raportFormal->file_raport) {
            Storage::disk('public')->delete("RaportFormal/{$anakId}/{$raportFormal->file_raport}");
        }

        if ($raportFormal->file_transkrip) {
            Storage::disk('public')->delete("RaportFormal/{$anakId}/{$raportFormal->file_transkrip}");
        }

        $raportFormal->delete();

        return response()->json([
            'success' => true,
            'message' => 'Raport Formal berhasil dihapus'
        ], 200);
    }
}