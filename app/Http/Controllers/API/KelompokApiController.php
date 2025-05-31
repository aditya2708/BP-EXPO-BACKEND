<?php

namespace App\Http\Controllers\API;

use App\Models\Anak;
use App\Models\Kelompok;
use App\Models\LevelAnakBinaan;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Resources\Kelompok\KelompokCollection;
use Illuminate\Support\Facades\Auth;

class KelompokApiController extends Controller
{
   /**
    * Menampilkan daftar Kelompok dengan pagination
    */
   public function index(Request $request)
  {
      $adminShelter = Auth::user()->adminShelter;

      if (!$adminShelter || !$adminShelter->shelter) {
          return response()->json([
              'success' => false,
              'message' => 'Shelter tidak ditemukan'
          ], 403);
      }

      $query = Kelompok::with([
        'shelter', 
        'levelAnakBinaan' => function($q) {
            $q->withDefault([
                'id_level_anak_binaan' => null,
                'nama_level_binaan' => 'Tidak Ada Level'
            ]);
        }
    ]);
      $query->where('id_shelter', $adminShelter->shelter->id_shelter);

      if ($request->has('search')) {
          $query->where('nama_kelompok', 'like', '%' . $request->search . '%');
      }

      if ($request->has('id_level_anak_binaan')) {
          $query->where('id_level_anak_binaan', $request->id_level_anak_binaan);
      }

      $totalKelompok = $query->count();

      $kelompok = $query->with(['shelter', 'levelAnakBinaan' => function($q) {
          $q->withDefault([
              'id_level_anak_binaan' => null,
              'nama_level_binaan' => 'Tidak Ada Level'
          ]);
      }])
      ->withCount('anak')
      ->latest()
      ->paginate($request->per_page ?? 10);

      return response()->json([
          'success'    => true,
          'message'    => 'Daftar Kelompok',
          'data'       => KelompokCollection::collection($kelompok),
          'pagination' => [
              'total'         => $kelompok->total(),
              'per_page'      => $kelompok->perPage(),
              'current_page'  => $kelompok->currentPage(),
              'last_page'     => $kelompok->lastPage(),
              'from'          => $kelompok->firstItem(),
              'to'            => $kelompok->lastItem()
          ],
          'summary'   => [
              'total_kelompok'   => $totalKelompok
          ]
      ], 200);
  }

 public function show($id)
{
    $kelompok = Kelompok::with([
        'shelter', 
        'levelAnakBinaan' => function($q) {
            $q->withDefault([
                'id_level_anak_binaan' => null,
                'nama_level_binaan' => 'Tidak Ada Level'
            ]);
        },
        'anak'
    ])
    ->withCount('anak')  // Add this to get anak count
    ->findOrFail($id);

    return response()->json([
        'success' => true,
        'message' => 'Detail Kelompok',
        'data' => new KelompokCollection($kelompok)
    ], 200);
}

   /**
    * Membuat Kelompok baru
    */
   public function store(Request $request)
   {
       // Dapatkan admin shelter yang sedang login
       $adminShelter = Auth::user()->adminShelter;

       // Pastikan admin shelter memiliki shelter
       if (!$adminShelter || !$adminShelter->shelter) {
           return response()->json([
               'success' => false,
               'message' => 'Shelter tidak ditemukan'
           ], 403);
       }

       $validatedData = $request->validate([
           'id_level_anak_binaan' => 'required|exists:level_as_anak_binaan,id_level_anak_binaan',
           'nama_kelompok' => [
               'required', 
               'string', 
               'max:255', 
               // Pastikan nama kelompok unik dalam shelter yang sama
               'unique:kelompok,nama_kelompok,NULL,id_kelompok,id_shelter,' . $adminShelter->shelter->id_shelter
           ],
           'jumlah_anggota' => 'nullable|integer|min:0'
       ]);

       // Gunakan id_shelter dari admin shelter yang login
       $validatedData['id_shelter'] = $adminShelter->shelter->id_shelter;

       $kelompok = Kelompok::create($validatedData);

       return response()->json([
           'success' => true,
           'message' => 'Kelompok berhasil dibuat',
           'data'    => new KelompokCollection($kelompok)
       ], 201);
   }

   /**
    * Memperbarui Kelompok
    */
   public function update(Request $request, $id)
   {
       // Dapatkan admin shelter yang sedang login
       $adminShelter = Auth::user()->adminShelter;

       // Pastikan admin shelter memiliki shelter
       if (!$adminShelter || !$adminShelter->shelter) {
           return response()->json([
               'success' => false,
               'message' => 'Shelter tidak ditemukan'
           ], 403);
       }

       $kelompok = Kelompok::findOrFail($id);

       // Pastikan kelompok milik shelter yang sama
       if ($kelompok->id_shelter !== $adminShelter->shelter->id_shelter) {
           return response()->json([
               'success' => false,
               'message' => 'Anda tidak memiliki izin untuk mengubah kelompok ini'
           ], 403);
       }

       $validatedData = $request->validate([
           'id_level_anak_binaan' => 'sometimes|exists:level_as_anak_binaan,id_level_anak_binaan',
           'nama_kelompok' => [
               'sometimes', 
               'string', 
               'max:255', 
               // Pastikan nama kelompok unik dalam shelter yang sama, kecuali untuk kelompok saat ini
               'unique:kelompok,nama_kelompok,' . $id . ',id_kelompok,id_shelter,' . $adminShelter->shelter->id_shelter
           ],
           'jumlah_anggota' => 'nullable|integer|min:0'
       ]);

       $kelompok->update($validatedData);

       return response()->json([
           'success' => true,
           'message' => 'Kelompok berhasil diperbarui',
           'data'    => new KelompokCollection($kelompok)
       ], 200);
   }

   /**
    * Menghapus Kelompok
    */
   public function destroy($id)
   {
       // Dapatkan admin shelter yang sedang login
       $adminShelter = Auth::user()->adminShelter;

       // Pastikan admin shelter memiliki shelter
       if (!$adminShelter || !$adminShelter->shelter) {
           return response()->json([
               'success' => false,
               'message' => 'Shelter tidak ditemukan'
           ], 403);
       }

       $kelompok = Kelompok::findOrFail($id);

       // Pastikan kelompok milik shelter yang sama
       if ($kelompok->id_shelter !== $adminShelter->shelter->id_shelter) {
           return response()->json([
               'success' => false,
               'message' => 'Anda tidak memiliki izin untuk menghapus kelompok ini'
           ], 403);
       }

       // Optional: Validate if kelompok has no anak before deleting
       if ($kelompok->anak()->count() > 0) {
           return response()->json([
               'success' => false,
               'message' => 'Tidak dapat menghapus kelompok yang memiliki anak'
           ], 400);
       }

       $kelompok->delete();

       return response()->json([
           'success' => true,
           'message' => 'Kelompok berhasil dihapus'
       ], 200);
   }

   /**
    * Mendapatkan daftar level anak binaan
    */
   public function getLevels()
   {
       $levels = LevelAnakBinaan::all();
       
       return response()->json([
           'success' => true,
           'message' => 'Daftar Level Anak Binaan',
           'data' => $levels
       ], 200);
   }

   /**
    * Mendapatkan daftar anak yang belum memiliki kelompok
    */
   public function getAvailableChildren($id_shelter)
   {
       // Dapatkan admin shelter yang sedang login
       $adminShelter = Auth::user()->adminShelter;

       // Pastikan admin shelter memiliki shelter
       if (!$adminShelter || !$adminShelter->shelter) {
           return response()->json([
               'success' => false,
               'message' => 'Shelter tidak ditemukan'
           ], 403);
       }

       // Pastikan shelter yang diminta adalah milik admin yang login
       if ($adminShelter->shelter->id_shelter != $id_shelter) {
           return response()->json([
               'success' => false,
               'message' => 'Anda tidak memiliki akses ke shelter ini'
           ], 403);
       }

       // Dapatkan anak yang belum memiliki kelompok
       $anakBinaan = Anak::where('id_shelter', $id_shelter)
                       ->whereNull('id_kelompok')
                       ->get();

       return response()->json([
           'success' => true,
           'message' => 'Daftar Anak Tersedia',
           'data' => $anakBinaan
       ], 200);
   }

   /**
    * Mendapatkan daftar anak dalam kelompok
    */
   public function getGroupChildren($id_kelompok)
   {
       // Dapatkan admin shelter yang sedang login
       $adminShelter = Auth::user()->adminShelter;

       // Pastikan admin shelter memiliki shelter
       if (!$adminShelter || !$adminShelter->shelter) {
           return response()->json([
               'success' => false,
               'message' => 'Shelter tidak ditemukan'
           ], 403);
       }

       $kelompok = Kelompok::findOrFail($id_kelompok);

       // Pastikan kelompok milik shelter admin yang login
       if ($kelompok->id_shelter !== $adminShelter->shelter->id_shelter) {
           return response()->json([
               'success' => false,
               'message' => 'Anda tidak memiliki akses ke kelompok ini'
           ], 403);
       }

       // Dapatkan anak dalam kelompok
       $anakBinaan = Anak::where('id_kelompok', $id_kelompok)->get();

       return response()->json([
           'success' => true,
           'message' => 'Daftar Anak Dalam Kelompok',
           'data' => $anakBinaan
       ], 200);
   }

   /**
    * Menambahkan anak ke kelompok
    */
   public function addChildToGroup(Request $request, $id_kelompok)
   {
       // Dapatkan admin shelter yang sedang login
       $adminShelter = Auth::user()->adminShelter;

       // Pastikan admin shelter memiliki shelter
       if (!$adminShelter || !$adminShelter->shelter) {
           return response()->json([
               'success' => false,
               'message' => 'Shelter tidak ditemukan'
           ], 403);
       }

       $kelompok = Kelompok::findOrFail($id_kelompok);

       // Pastikan kelompok milik shelter admin yang login
       if ($kelompok->id_shelter !== $adminShelter->shelter->id_shelter) {
           return response()->json([
               'success' => false,
               'message' => 'Anda tidak memiliki akses ke kelompok ini'
           ], 403);
       }

       $validatedData = $request->validate([
           'id_anak' => 'required|exists:anak,id_anak'
       ]);

       $anak = Anak::findOrFail($validatedData['id_anak']);

       // Pastikan anak ada di shelter yang sama
       if ($anak->id_shelter !== $adminShelter->shelter->id_shelter) {
           return response()->json([
               'success' => false,
               'message' => 'Anak tidak berada di shelter yang sama'
           ], 400);
       }

       // Update id_kelompok anak
       $anak->id_kelompok = $id_kelompok;
       $anak->save();

       // Update jumlah anggota kelompok
       $kelompok->jumlah_anggota = $kelompok->anak()->count();
       $kelompok->save();

       return response()->json([
           'success' => true,
           'message' => 'Anak berhasil ditambahkan ke kelompok',
           'data' => $anak
       ], 200);
   }

   /**
    * Menghapus anak dari kelompok
    */
   public function removeChildFromGroup(Request $request, $id_kelompok, $id_anak)
   {
       // Dapatkan admin shelter yang sedang login
       $adminShelter = Auth::user()->adminShelter;

       // Pastikan admin shelter memiliki shelter
       if (!$adminShelter || !$adminShelter->shelter) {
           return response()->json([
               'success' => false,
               'message' => 'Shelter tidak ditemukan'
           ], 403);
       }

       $kelompok = Kelompok::findOrFail($id_kelompok);

       // Pastikan kelompok milik shelter admin yang login
       if ($kelompok->id_shelter !== $adminShelter->shelter->id_shelter) {
           return response()->json([
               'success' => false,
               'message' => 'Anda tidak memiliki akses ke kelompok ini'
           ], 403);
       }

       $anak = Anak::findOrFail($id_anak);

       // Pastikan anak ada di kelompok yang dimaksud
       if ($anak->id_kelompok != $id_kelompok) {
           return response()->json([
               'success' => false,
               'message' => 'Anak tidak ada dalam kelompok ini'
           ], 400);
       }

       // Hapus anak dari kelompok
       $anak->id_kelompok = null;
       $anak->save();

       // Update jumlah anggota kelompok
       $kelompok->jumlah_anggota = $kelompok->anak()->count();
       $kelompok->save();

       return response()->json([
           'success' => true,
           'message' => 'Anak berhasil dihapus dari kelompok',
           'data' => $anak
       ], 200);
   }

   /**
    * Memindahkan anak ke shelter lain
    */
   public function moveChildToShelter(Request $request, $id_anak)
   {
       // Dapatkan admin shelter yang sedang login
       $adminShelter = Auth::user()->adminShelter;

       // Pastikan admin shelter memiliki shelter
       if (!$adminShelter || !$adminShelter->shelter) {
           return response()->json([
               'success' => false,
               'message' => 'Shelter tidak ditemukan'
           ], 403);
       }

       $validatedData = $request->validate([
           'id_shelter_baru' => 'required|exists:shelter,id_shelter'
       ]);

       $anak = Anak::findOrFail($id_anak);

       // Pastikan anak ada di shelter admin yang login
       if ($anak->id_shelter !== $adminShelter->shelter->id_shelter) {
           return response()->json([
               'success' => false,
               'message' => 'Anak tidak berada di shelter anda'
           ], 403);
       }

       // Jika anak memiliki kelompok, update jumlah anggota kelompok lama
       if ($anak->id_kelompok) {
           $oldKelompok = Kelompok::findOrFail($anak->id_kelompok);
           $anak->id_kelompok = null; // Hapus dari kelompok lama
           
           // Simpan perubahan
           $anak->save();
           
           // Update jumlah anggota kelompok lama
           $oldKelompok->jumlah_anggota = $oldKelompok->anak()->count();
           $oldKelompok->save();
       }

       // Pindahkan anak ke shelter baru
       $anak->id_shelter = $validatedData['id_shelter_baru'];
       $anak->save();

       return response()->json([
           'success' => true,
           'message' => 'Anak berhasil dipindahkan ke shelter baru',
           'data' => $anak
       ], 200);
   }
}