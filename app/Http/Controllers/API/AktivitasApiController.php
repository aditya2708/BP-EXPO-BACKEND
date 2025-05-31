<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Aktivitas;
use App\Models\Shelter;
use App\Models\Kelompok;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Throwable;

class AktivitasApiController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $per_page        = $request->input('per_page', 10);
            $id_shelter      = $request->input('id_shelter');
            $search          = $request->input('search');
            $jenis_kegiatan  = $request->input('jenis_kegiatan');

            $query = Aktivitas::with(['shelter']);

            // Filter by shelter if provided or by admin shelter
            if ($id_shelter) {
                $query->where('id_shelter', $id_shelter);
            } else {
                $user = $request->user();
                $adminShelter = $user->adminShelter;
                if ($adminShelter && $adminShelter->id_shelter) {
                    $query->where('id_shelter', $adminShelter->id_shelter);
                }
            }

            // Filter by search
            if ($search) {
                $query->where(function($q) use ($search) {
                    $q->where('jenis_kegiatan', 'LIKE', "%{$search}%")
                      ->orWhere('materi', 'LIKE', "%{$search}%")
                      ->orWhere('nama_kelompok', 'LIKE', "%{$search}%");
                });
            }

            // Filter by jenis kegiatan
            if ($jenis_kegiatan) {
                $query->where('jenis_kegiatan', $jenis_kegiatan);
            }

            $query->orderBy('created_at', 'desc');

            $aktivitas = $query->paginate($per_page);

            return response()->json([
                'success'    => true,
                'message'    => 'Data aktivitas berhasil diambil',
                'data'       => $aktivitas->items(),
                'pagination' => [
                    'current_page' => $aktivitas->currentPage(),
                    'last_page'    => $aktivitas->lastPage(),
                    'per_page'     => $aktivitas->perPage(),
                    'total'        => $aktivitas->total(),
                ],
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data aktivitas: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            $user = $request->user();
            $adminShelter = $user->adminShelter;
            if (! $adminShelter || ! $adminShelter->id_shelter) {
                return response()->json([
                    'success' => false,
                    'message' => 'Admin shelter tidak ditemukan atau tidak terkait dengan shelter'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'jenis_kegiatan' => 'required|string',
                'tanggal'        => 'required|date',
                'materi'         => 'required|string',
                'foto_1'         => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
                'foto_2'         => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
                'foto_3'         => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            ]);

            if ($request->jenis_kegiatan === 'Bimbel') {
                $validator->addRules([
                    'level'          => 'required|string',
                    'nama_kelompok'  => 'required|string',
                ]);
            }

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors'  => $validator->errors(),
                ], 422);
            }

            $aktivitas = new Aktivitas();
            $aktivitas->id_shelter      = $adminShelter->id_shelter;
            $aktivitas->jenis_kegiatan  = $request->jenis_kegiatan;
            $aktivitas->tanggal         = $request->tanggal;
            $aktivitas->materi          = $request->materi;
            if ($request->jenis_kegiatan === 'Bimbel') {
                $aktivitas->level         = $request->level;
                $aktivitas->nama_kelompok = $request->nama_kelompok;
            } else {
                $aktivitas->level         = $request->level ?? '-';
                $aktivitas->nama_kelompok = $request->nama_kelompok ?? '-';
            }

            $aktivitas->save();

            // Process and save photos
            $this->processPhotos($request, $aktivitas);
            $aktivitas->save();

            $aktivitas = Aktivitas::with(['shelter'])->find($aktivitas->id_aktivitas);

            return response()->json([
                'success' => true,
                'message' => 'Aktivitas berhasil ditambahkan',
                'data'    => $aktivitas,
            ], 201);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan aktivitas: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        try {
            $aktivitas = Aktivitas::find($id);
            if (! $aktivitas) {
                return response()->json([
                    'success' => false,
                    'message' => 'Aktivitas tidak ditemukan'
                ], 404);
            }

            $user = $request->user();
            $adminShelter = $user->adminShelter;
            if (! $adminShelter || $adminShelter->id_shelter != $aktivitas->id_shelter) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda tidak memiliki izin untuk mengubah aktivitas ini'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'jenis_kegiatan' => 'required|string',
                'tanggal'        => 'required|date',
                'materi'         => 'required|string',
                'foto_1'         => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
                'foto_2'         => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
                'foto_3'         => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
                'hapus_foto_1'   => 'nullable|boolean',
                'hapus_foto_2'   => 'nullable|boolean',
                'hapus_foto_3'   => 'nullable|boolean',
            ]);

            if ($request->jenis_kegiatan === 'Bimbel') {
                $validator->addRules([
                    'level'          => 'required|string',
                    'nama_kelompok'  => 'required|string',
                ]);
            }

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors'  => $validator->errors(),
                ], 422);
            }

            $aktivitas->jenis_kegiatan  = $request->jenis_kegiatan;
            $aktivitas->tanggal         = $request->tanggal;
            $aktivitas->materi          = $request->materi;
            if ($request->jenis_kegiatan === 'Bimbel') {
                $aktivitas->level         = $request->level;
                $aktivitas->nama_kelompok = $request->nama_kelompok;
            } else {
                $aktivitas->level         = $request->level ?? '-';
                $aktivitas->nama_kelompok = $request->nama_kelompok ?? '-';
            }

            // Delete old photos if requested
            foreach (['foto_1', 'foto_2', 'foto_3'] as $field) {
                $hapusField = 'hapus_' . $field;
                if ($request->boolean($hapusField) && $aktivitas->$field) {
                    $path = "public/Aktivitas/{$aktivitas->id_aktivitas}/{$aktivitas->$field}";
                    if (Storage::exists($path)) {
                        Storage::delete($path);
                    }
                    $aktivitas->$field = null;
                }
            }

            // Process and save new photos
            $this->processPhotos($request, $aktivitas);
            $aktivitas->save();

            // Reload with photo URLs
            $aktivitas = Aktivitas::with(['shelter'])->find($aktivitas->id_aktivitas);
            foreach (['foto_1', 'foto_2', 'foto_3'] as $field) {
                $aktivitas->{$field . '_url'} = $aktivitas->$field
                    ? url("storage/Aktivitas/{$aktivitas->id_aktivitas}/{$aktivitas->$field}")
                    : null;
            }

            return response()->json([
                'success' => true,
                'message' => 'Aktivitas berhasil diperbarui',
                'data'    => $aktivitas,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui aktivitas: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Process uploaded photos and assign them to the aktivitas.
     */
    private function processPhotos(Request $request, Aktivitas $aktivitas)
    {
        foreach (['foto_1', 'foto_2', 'foto_3'] as $field) {
            if ($request->hasFile($field)) {
                $file = $request->file($field);
                $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
                $path = "public/Aktivitas/{$aktivitas->id_aktivitas}";
                $file->storeAs($path, $filename);
                $aktivitas->$field = $filename;
            }
        }
    }
    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $aktivitas = Aktivitas::with(['shelter'])->find($id);
        if (! $aktivitas) {
            return response()->json(['success' => false, 'message' => 'Aktivitas tidak ditemukan'], 404);
        }
        return response()->json(['success' => true, 'data' => $aktivitas]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $aktivitas = Aktivitas::find($id);
        if (! $aktivitas) {
            return response()->json(['success' => false, 'message' => 'Aktivitas tidak ditemukan'], 404);
        }
        // Optionally delete related photos
        Storage::deleteDirectory("public/Aktivitas/{$aktivitas->id_aktivitas}");
        $aktivitas->delete();
        return response()->json(['success' => true, 'message' => 'Aktivitas berhasil dihapus']);
    }

    /**
     * Upload photos to existing aktivitas.
     */
    public function uploadFoto(Request $request, $id)
    {
        $aktivitas = Aktivitas::find($id);
        if (! $aktivitas) {
            return response()->json(['success' => false, 'message' => 'Aktivitas tidak ditemukan'], 404);
        }
        $validator = Validator::make($request->all(), [
            'foto_1' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'foto_2' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'foto_3' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Validasi gagal', 'errors' => $validator->errors()], 422);
        }
        $this->processPhotos($request, $aktivitas);
        $aktivitas->save();
        return response()->json(['success' => true, 'message' => 'Foto berhasil diupload', 'data' => $aktivitas]);
    }
}
