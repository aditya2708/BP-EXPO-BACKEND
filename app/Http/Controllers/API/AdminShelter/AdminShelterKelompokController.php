<?php

namespace App\Http\Controllers\API\AdminShelter;

use App\Models\Anak;
use App\Models\Kelompok;
use App\Models\LevelAnakBinaan;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Resources\Kelompok\KelompokCollection;
use Illuminate\Support\Facades\Auth;

class AdminShelterKelompokController extends Controller
{
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
    ->withCount('anak')
    ->findOrFail($id);

    return response()->json([
        'success' => true,
        'message' => 'Detail Kelompok',
        'data' => new KelompokCollection($kelompok)
    ], 200);
}

   public function store(Request $request)
   {
       $adminShelter = Auth::user()->adminShelter;

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
               'unique:kelompok,nama_kelompok,NULL,id_kelompok,id_shelter,' . $adminShelter->shelter->id_shelter
           ],
           'jumlah_anggota' => 'nullable|integer|min:0'
       ]);

       $validatedData['id_shelter'] = $adminShelter->shelter->id_shelter;

       $kelompok = Kelompok::create($validatedData);

       return response()->json([
           'success' => true,
           'message' => 'Kelompok berhasil dibuat',
           'data'    => new KelompokCollection($kelompok)
       ], 201);
   }

   public function update(Request $request, $id)
   {
       $adminShelter = Auth::user()->adminShelter;

       if (!$adminShelter || !$adminShelter->shelter) {
           return response()->json([
               'success' => false,
               'message' => 'Shelter tidak ditemukan'
           ], 403);
       }

       $kelompok = Kelompok::findOrFail($id);

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
               'unique:kelompok,nama_kelompok,' . $id . ',id_kelompok,id_shelter,' . $adminShelter->shelter->id_shelter
           ],
           'jumlah_anggota' => 'nullable|integer|min:0'
       ]);

       if (isset($validatedData['id_level_anak_binaan']) && $validatedData['id_level_anak_binaan'] !== $kelompok->id_level_anak_binaan) {
           $kelompok->anak()->update(['id_level_anak_binaan' => $validatedData['id_level_anak_binaan']]);
       }

       $kelompok->update($validatedData);

       return response()->json([
           'success' => true,
           'message' => 'Kelompok berhasil diperbarui',
           'data'    => new KelompokCollection($kelompok)
       ], 200);
   }

   public function destroy($id)
   {
       $adminShelter = Auth::user()->adminShelter;

       if (!$adminShelter || !$adminShelter->shelter) {
           return response()->json([
               'success' => false,
               'message' => 'Shelter tidak ditemukan'
           ], 403);
       }

       $kelompok = Kelompok::findOrFail($id);

       if ($kelompok->id_shelter !== $adminShelter->shelter->id_shelter) {
           return response()->json([
               'success' => false,
               'message' => 'Anda tidak memiliki izin untuk menghapus kelompok ini'
           ], 403);
       }

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

   public function getLevels()
   {
       $levels = LevelAnakBinaan::all();
       
       return response()->json([
           'success' => true,
           'message' => 'Daftar Level Anak Binaan',
           'data' => $levels
       ], 200);
   }

   public function getAvailableChildren($id_shelter)
   {
       $adminShelter = Auth::user()->adminShelter;

       if (!$adminShelter || !$adminShelter->shelter) {
           return response()->json([
               'success' => false,
               'message' => 'Shelter tidak ditemukan'
           ], 403);
       }

       if ($adminShelter->shelter->id_shelter != $id_shelter) {
           return response()->json([
               'success' => false,
               'message' => 'Anda tidak memiliki akses ke shelter ini'
           ], 403);
       }

       $anakBinaan = Anak::where('id_shelter', $id_shelter)
                       ->whereNull('id_kelompok')
                       ->with('anakPendidikan')
                       ->get();

       return response()->json([
           'success' => true,
           'message' => 'Daftar Anak Tersedia',
           'data' => $anakBinaan
       ], 200);
   }

   public function getGroupChildren($id_kelompok)
   {
       $adminShelter = Auth::user()->adminShelter;

       if (!$adminShelter || !$adminShelter->shelter) {
           return response()->json([
               'success' => false,
               'message' => 'Shelter tidak ditemukan'
           ], 403);
       }

       $kelompok = Kelompok::findOrFail($id_kelompok);

       if ($kelompok->id_shelter !== $adminShelter->shelter->id_shelter) {
           return response()->json([
               'success' => false,
               'message' => 'Anda tidak memiliki akses ke kelompok ini'
           ], 403);
       }

       $anakBinaan = Anak::where('id_kelompok', $id_kelompok)->get();

       return response()->json([
           'success' => true,
           'message' => 'Daftar Anak Dalam Kelompok',
           'data' => $anakBinaan
       ], 200);
   }

   private function validateEducationLevelCompatibility($anak, $kelompok)
   {
       if (!$anak->anakPendidikan || !$kelompok->levelAnakBinaan) {
           return true;
       }

       $jenjang = strtolower(trim($anak->anakPendidikan->jenjang));
       $levelName = strtolower(trim($kelompok->levelAnakBinaan->nama_level_binaan));

       $compatibility = [
           'belum_sd' => ['tk', 'paud', 'early', 'dini', 'kelas 1', 'kelas 2', 'kelas 3'],
           'sd' => ['sd', 'elementary', 'dasar', 'kelas 1', 'kelas 2', 'kelas 3', 'kelas 4', 'kelas 5', 'kelas 6'],
           'smp' => ['smp', 'mts', 'junior', 'menengah pertama', 'kelas 7', 'kelas 8', 'kelas 9'],
           'sma' => ['sma', 'smk', 'ma', 'senior', 'menengah atas', 'kelas 10', 'kelas 11', 'kelas 12'],
           'perguruan_tinggi' => ['universitas', 'college', 'tinggi', 'sarjana', 'semester']
       ];

       if (!isset($compatibility[$jenjang])) {
           return true;
       }

       foreach ($compatibility[$jenjang] as $keyword) {
           if (strpos($levelName, $keyword) !== false) {
               return true;
           }
       }

       return false;
   }

   public function addChildToGroup(Request $request, $id_kelompok)
   {
       $adminShelter = Auth::user()->adminShelter;

       if (!$adminShelter || !$adminShelter->shelter) {
           return response()->json([
               'success' => false,
               'message' => 'Shelter tidak ditemukan'
           ], 403);
       }

       $kelompok = Kelompok::with('levelAnakBinaan')->findOrFail($id_kelompok);

       if ($kelompok->id_shelter !== $adminShelter->shelter->id_shelter) {
           return response()->json([
               'success' => false,
               'message' => 'Anda tidak memiliki akses ke kelompok ini'
           ], 403);
       }

       $validatedData = $request->validate([
           'id_anak' => 'required|exists:anak,id_anak'
       ]);

       $anak = Anak::with('anakPendidikan')->findOrFail($validatedData['id_anak']);

       if ($anak->id_shelter !== $adminShelter->shelter->id_shelter) {
           return response()->json([
               'success' => false,
               'message' => 'Anak tidak berada di shelter yang sama'
           ], 400);
       }

       if ($anak->id_kelompok) {
           return response()->json([
               'success' => false,
               'message' => 'Anak sudah berada dalam kelompok lain'
           ], 400);
       }

       if (!$this->validateEducationLevelCompatibility($anak, $kelompok)) {
           return response()->json([
               'success' => false,
               'message' => 'Tingkat pendidikan anak tidak sesuai dengan level kelompok'
           ], 400);
       }

       $anak->id_kelompok = $id_kelompok;
       $anak->id_level_anak_binaan = $kelompok->id_level_anak_binaan;
       $anak->save();

       $kelompok->jumlah_anggota = $kelompok->anak()->count();
       $kelompok->save();

       return response()->json([
           'success' => true,
           'message' => 'Anak berhasil ditambahkan ke kelompok',
           'data' => $anak
       ], 200);
   }

   public function removeChildFromGroup(Request $request, $id_kelompok, $id_anak)
   {
       $adminShelter = Auth::user()->adminShelter;

       if (!$adminShelter || !$adminShelter->shelter) {
           return response()->json([
               'success' => false,
               'message' => 'Shelter tidak ditemukan'
           ], 403);
       }

       $kelompok = Kelompok::findOrFail($id_kelompok);

       if ($kelompok->id_shelter !== $adminShelter->shelter->id_shelter) {
           return response()->json([
               'success' => false,
               'message' => 'Anda tidak memiliki akses ke kelompok ini'
           ], 403);
       }

       $anak = Anak::findOrFail($id_anak);

       if ($anak->id_kelompok != $id_kelompok) {
           return response()->json([
               'success' => false,
               'message' => 'Anak tidak ada dalam kelompok ini'
           ], 400);
       }

       $anak->id_kelompok = null;
       $anak->id_level_anak_binaan = null;
       $anak->save();

       $kelompok->jumlah_anggota = $kelompok->anak()->count();
       $kelompok->save();

       return response()->json([
           'success' => true,
           'message' => 'Anak berhasil dihapus dari kelompok',
           'data' => $anak
       ], 200);
   }

   public function moveChildToShelter(Request $request, $id_anak)
   {
       $adminShelter = Auth::user()->adminShelter;

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

       if ($anak->id_shelter !== $adminShelter->shelter->id_shelter) {
           return response()->json([
               'success' => false,
               'message' => 'Anak tidak berada di shelter anda'
           ], 403);
       }

       if ($anak->id_kelompok) {
           $oldKelompok = Kelompok::findOrFail($anak->id_kelompok);
           $anak->id_kelompok = null;
           $anak->id_level_anak_binaan = null;
           
           $anak->save();
           
           $oldKelompok->jumlah_anggota = $oldKelompok->anak()->count();
           $oldKelompok->save();
       }

       $anak->id_shelter = $validatedData['id_shelter_baru'];
       $anak->save();

       return response()->json([
           'success' => true,
           'message' => 'Anak berhasil dipindahkan ke shelter baru',
           'data' => $anak
       ], 200);
   }
}