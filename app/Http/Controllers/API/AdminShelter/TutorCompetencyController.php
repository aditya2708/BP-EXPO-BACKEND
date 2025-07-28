<?php

namespace App\Http\Controllers\API\AdminShelter;

use App\Http\Controllers\Controller;
use App\Models\TutorCompetency;
use App\Models\JenisKompetensi;
use App\Models\Tutor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class TutorCompetencyController extends Controller
{
    public function index($tutorId)
    {
        $user = Auth::user();
        
        if (!$user->adminShelter) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $tutor = Tutor::where('id_shelter', $user->adminShelter->shelter->id_shelter)
                     ->findOrFail($tutorId);

        $competencies = TutorCompetency::where('id_tutor', $tutorId)
                                      ->with('jenisKompetensi')
                                      ->latest()
                                      ->get();

        return response()->json([
            'success' => true,
            'data' => $competencies
        ]);
    }

    public function show($tutorId, $id)
    {
        $user = Auth::user();
        
        if (!$user->adminShelter) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $tutor = Tutor::where('id_shelter', $user->adminShelter->shelter->id_shelter)
                     ->findOrFail($tutorId);

        $competency = TutorCompetency::where('id_tutor', $tutorId)
                                    ->with('jenisKompetensi')
                                    ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $competency
        ]);
    }

    public function store(Request $request, $tutorId)
    {
        $user = Auth::user();
        
        if (!$user->adminShelter) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $tutor = Tutor::where('id_shelter', $user->adminShelter->shelter->id_shelter)
                     ->findOrFail($tutorId);

        $validatedData = $request->validate([
            'id_jenis_kompetensi' => 'required|exists:jenis_kompetensi,id_jenis_kompetensi',
            'nama_competency' => 'required|string|max:255',
            'tanggal_diperoleh' => 'required|date',
            'tanggal_kadaluarsa' => 'nullable|date|after:tanggal_diperoleh',
            'instansi_penerbit' => 'required|string|max:255',
            'nomor_sertifikat' => 'nullable|string|max:255',
            'deskripsi' => 'nullable|string',
            'file_sertifikat' => 'nullable|file|mimes:pdf|max:2048'
        ]);

        $validatedData['id_tutor'] = $tutorId;

        $competency = TutorCompetency::create($validatedData);

        if ($request->hasFile('file_sertifikat')) {
            $folderPath = "TutorCompetency/{$tutorId}";
            $fileName = $request->file('file_sertifikat')->getClientOriginalName();
            $request->file('file_sertifikat')->storeAs($folderPath, $fileName, 'public');
            $competency->update(['file_sertifikat' => $fileName]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Kompetensi berhasil ditambahkan',
            'data' => $competency->load('jenisKompetensi')
        ], 201);
    }

    public function update(Request $request, $tutorId, $id)
    {
        $user = Auth::user();
        
        if (!$user->adminShelter) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $tutor = Tutor::where('id_shelter', $user->adminShelter->shelter->id_shelter)
                     ->findOrFail($tutorId);

        $competency = TutorCompetency::where('id_tutor', $tutorId)->findOrFail($id);

        $validatedData = $request->validate([
            'id_jenis_kompetensi' => 'sometimes|required|exists:jenis_kompetensi,id_jenis_kompetensi',
            'nama_competency' => 'sometimes|required|string|max:255',
            'tanggal_diperoleh' => 'sometimes|required|date',
            'tanggal_kadaluarsa' => 'nullable|date|after:tanggal_diperoleh',
            'instansi_penerbit' => 'sometimes|required|string|max:255',
            'nomor_sertifikat' => 'nullable|string|max:255',
            'deskripsi' => 'nullable|string',
            'file_sertifikat' => 'nullable|file|mimes:pdf|max:2048'
        ]);

        if ($request->hasFile('file_sertifikat')) {
            if ($competency->file_sertifikat) {
                Storage::disk('public')->delete("TutorCompetency/{$tutorId}/{$competency->file_sertifikat}");
            }

            $folderPath = "TutorCompetency/{$tutorId}";
            $fileName = $request->file('file_sertifikat')->getClientOriginalName();
            $request->file('file_sertifikat')->storeAs($folderPath, $fileName, 'public');
            $validatedData['file_sertifikat'] = $fileName;
        }

        $competency->update($validatedData);

        return response()->json([
            'success' => true,
            'message' => 'Kompetensi berhasil diperbarui',
            'data' => $competency->fresh()->load('jenisKompetensi')
        ]);
    }

    public function destroy($tutorId, $id)
    {
        $user = Auth::user();
        
        if (!$user->adminShelter) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $tutor = Tutor::where('id_shelter', $user->adminShelter->shelter->id_shelter)
                     ->findOrFail($tutorId);

        $competency = TutorCompetency::where('id_tutor', $tutorId)->findOrFail($id);

        if ($competency->file_sertifikat) {
            Storage::disk('public')->delete("TutorCompetency/{$tutorId}/{$competency->file_sertifikat}");
        }

        $competency->delete();

        return response()->json([
            'success' => true,
            'message' => 'Kompetensi berhasil dihapus'
        ]);
    }

    public function getJenisKompetensi()
    {
        $jenisKompetensi = JenisKompetensi::where('is_active', true)->get();

        return response()->json([
            'success' => true,
            'data' => $jenisKompetensi
        ]);
    }
}