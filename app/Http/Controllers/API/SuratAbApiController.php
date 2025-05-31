<?php

namespace App\Http\Controllers\API;

use App\Models\SuratAb;
use App\Models\Anak;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Resources\SuratAb\SuratAbCollection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SuratAbApiController extends Controller
{
    /**
     * Menampilkan daftar Surat Anak Binaan dengan pagination
     */
    public function index(Request $request)
    {
        $query = SuratAb::query();

        // Filter berdasarkan id_anak jika ada parameter
        if ($request->has('id_anak')) {
            $query->where('id_anak', $request->id_anak);
        }

        // Hitung total surat
        $totalSurat = SuratAb::count();

        // Eager loading relasi anak
        $suratAb = $query->with('anak')
                         ->latest()
                         ->paginate($request->per_page ?? 10);

        return response()->json([
            'success'    => true,
            'message'    => 'Daftar Surat Anak Binaan',
            'data'       => SuratAbCollection::collection($suratAb),
            'pagination' => [
                'total'         => $suratAb->total(),
                'per_page'      => $suratAb->perPage(),
                'current_page'  => $suratAb->currentPage(),
                'last_page'     => $suratAb->lastPage(),
                'from'          => $suratAb->firstItem(),
                'to'            => $suratAb->lastItem()
            ],
            'summary'   => [
                'total_surat'   => $totalSurat
            ]
        ], 200);
    }

    /**
     * Menampilkan detail Surat Anak Binaan berdasarkan ID
     */
    public function show($id)
    {
        $suratAb = SuratAb::with('anak')
                           ->findOrFail($id);

        return response()->json([
            'success' => true,
            'message' => 'Detail Surat Anak Binaan',
            'data'    => new SuratAbCollection($suratAb)
        ], 200);
    }

    /**
     * Membuat Surat Anak Binaan baru
     */
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'id_anak' => 'required|exists:anak,id_anak',
            'pesan' => 'required|string',
            'foto' => 'nullable|image|max:2048', // maksimal 2MB
            'tanggal' => 'nullable|date',
            'is_read' => 'nullable|boolean'
        ]);

        $suratAb = new SuratAb();
        $suratAb->fill($validatedData);

        // Handle foto upload
        if ($request->hasFile('foto')) {
            $file = $request->file('foto');
            $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs("SuratAb/{$validatedData['id_anak']}", $filename, 'public');
            $suratAb->foto = $filename;
        }

        $suratAb->tanggal = $suratAb->tanggal ?? now();
        $suratAb->save();

        return response()->json([
            'success' => true,
            'message' => 'Surat Anak Binaan berhasil dibuat',
            'data'    => new SuratAbCollection($suratAb)
        ], 201);
    }

    /**
     * Memperbarui Surat Anak Binaan
     */
    public function update(Request $request, $id)
    {
        $suratAb = SuratAb::findOrFail($id);

        $validatedData = $request->validate([
            'id_anak' => 'sometimes|exists:anak,id_anak',
            'pesan' => 'sometimes|string',
            'foto' => 'nullable|image|max:2048', // maksimal 2MB
            'tanggal' => 'nullable|date',
            'is_read' => 'nullable|boolean'
        ]);

        // Handle foto upload
        if ($request->hasFile('foto')) {
            // Hapus foto lama jika ada
            if ($suratAb->foto) {
                Storage::disk('public')->delete("SuratAb/{$suratAb->id_anak}/{$suratAb->foto}");
            }

            $file = $request->file('foto');
            $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs("SuratAb/" . (isset($validatedData['id_anak']) ? $validatedData['id_anak'] : $suratAb->id_anak), $filename, 'public');
            $validatedData['foto'] = $filename;
        }

        $suratAb->update($validatedData);

        return response()->json([
            'success' => true,
            'message' => 'Surat Anak Binaan berhasil diperbarui',
            'data'    => new SuratAbCollection($suratAb)
        ], 200);
    }

    /**
     * Menghapus Surat Anak Binaan
     */
    public function destroy($id)
    {
        $suratAb = SuratAb::findOrFail($id);
        
        // Hapus foto jika ada
        if ($suratAb->foto) {
            Storage::disk('public')->delete("SuratAb/{$suratAb->id_anak}/{$suratAb->foto}");
        }

        $suratAb->delete();

        return response()->json([
            'success' => true,
            'message' => 'Surat Anak Binaan berhasil dihapus'
        ], 200);
    }
}