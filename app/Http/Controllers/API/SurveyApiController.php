<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Anak;
use App\Models\Keluarga;
use App\Models\Survey;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Throwable;

class SurveyApiController extends Controller
{
    /**
     * Get a list of families that don't have a survey yet
     * or all surveys if queried
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            $adminShelter = $user->adminShelter;
            
            if (!$adminShelter || !$adminShelter->id_shelter) {
                return response()->json([
                    'success' => false,
                    'message' => 'Admin shelter tidak ditemukan atau tidak terkait dengan shelter'
                ], 403);
            }
            
            $id_shelter = $adminShelter->id_shelter;
            $show_all = $request->input('show_all', false);
            $per_page = $request->input('per_page', 10);
            $search = $request->input('search', '');
            
            // Get all keluarga from admin's shelter
            $keluargaQuery = Keluarga::with(['kacab', 'wilbin', 'shelter'])
                ->where('id_shelter', $id_shelter);
                
            // Add search filter if provided
            if ($search) {
                $keluargaQuery->where(function($q) use ($search) {
                    $q->where('no_kk', 'LIKE', "%{$search}%")
                      ->orWhere('kepala_keluarga', 'LIKE', "%{$search}%");
                });
            }
            
            if (!$show_all) {
                // Only get families without survey
                $keluargaQuery->whereDoesntHave('surveys');
                $data = $keluargaQuery->paginate($per_page);
                
                return response()->json([
                    'success' => true,
                    'message' => 'Data keluarga tanpa survey berhasil diambil',
                    'data' => $data->items(),
                    'pagination' => [
                        'current_page' => $data->currentPage(),
                        'last_page' => $data->lastPage(),
                        'per_page' => $data->perPage(),
                        'total' => $data->total()
                    ]
                ]);
            } else {
                // Get all surveys for families in this shelter
                $surveysQuery = Survey::with(['keluarga.kacab', 'keluarga.wilbin', 'keluarga.shelter'])
                    ->whereHas('keluarga', function($q) use ($id_shelter) {
                        $q->where('id_shelter', $id_shelter);
                    });
                
                // Add search filter for surveys if provided
                if ($search) {
                    $surveysQuery->whereHas('keluarga', function($q) use ($search) {
                        $q->where('no_kk', 'LIKE', "%{$search}%")
                          ->orWhere('kepala_keluarga', 'LIKE', "%{$search}%");
                    });
                }
                
                $data = $surveysQuery->paginate($per_page);
                
                return response()->json([
                    'success' => true,
                    'message' => 'Data survey berhasil diambil',
                    'data' => $data->items(),
                    'pagination' => [
                        'current_page' => $data->currentPage(),
                        'last_page' => $data->lastPage(),
                        'per_page' => $data->perPage(),
                        'total' => $data->total()
                    ]
                ]);
            }
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get survey details for a specific family
     */
    public function show(Request $request, $id_keluarga)
    {
        try {
            $user = $request->user();
            $adminShelter = $user->adminShelter;
            
            if (!$adminShelter || !$adminShelter->id_shelter) {
                return response()->json([
                    'success' => false,
                    'message' => 'Admin shelter tidak ditemukan atau tidak terkait dengan shelter'
                ], 403);
            }
            
            $id_shelter = $adminShelter->id_shelter;
            
            // Check if keluarga exists and belongs to admin's shelter
            $keluarga = Keluarga::where('id_keluarga', $id_keluarga)
                ->where('id_shelter', $id_shelter)
                ->first();
                
            if (!$keluarga) {
                return response()->json([
                    'success' => false,
                    'message' => 'Keluarga tidak ditemukan atau tidak terkait dengan shelter Anda'
                ], 404);
            }
            
            // Get survey data if exists
            $survey = Survey::where('id_keluarga', $id_keluarga)->first();
            
            return response()->json([
                'success' => true,
                'message' => 'Data survey berhasil diambil',
                'data' => [
                    'keluarga' => $keluarga,
                    'survey' => $survey
                ]
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data survey: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create or update survey data for a family
     */
    public function store(Request $request, $id_keluarga)
    {
        try {
            $user = $request->user();
            $adminShelter = $user->adminShelter;
            
            if (!$adminShelter || !$adminShelter->id_shelter) {
                return response()->json([
                    'success' => false,
                    'message' => 'Admin shelter tidak ditemukan atau tidak terkait dengan shelter'
                ], 403);
            }
            
            $id_shelter = $adminShelter->id_shelter;
            
            // Check if keluarga exists and belongs to admin's shelter
            $keluarga = Keluarga::where('id_keluarga', $id_keluarga)
                ->where('id_shelter', $id_shelter)
                ->first();
                
            if (!$keluarga) {
                return response()->json([
                    'success' => false,
                    'message' => 'Keluarga tidak ditemukan atau tidak terkait dengan shelter Anda'
                ], 404);
            }
            
            // Validate required fields based on the request body
            $validator = Validator::make($request->all(), [
                'pendidikan_kepala_keluarga' => 'required|string|in:Tidak Sekolah,Sekolah Dasar,SMP/MTS/SEDERAJAT,SMK/SMA/MA/SEDERAJAT,DIPLOMA I,DIPLOMA II,DIPLOMA III,STRATA-1,STRATA-2,STRATA-3,LAINNYA',
                'jumlah_tanggungan' => 'required|integer',
                'pekerjaan_kepala_keluarga' => 'required|string|in:Petani,Nelayan,Peternak,PNS NON Dosen/Guru,Guru PNS,Guru Non PNS,Karyawan Swasta,Buruh,Wiraswasta,Wirausaha,Pedagang Kecil,Pedagang Besar,Pensiunan,Tidak Bekerja,Sudah Meninggal,Lainnya',
                'penghasilan' => 'required|string|in:dibawah_500k,500k_1500k,1500k_2500k,2500k_3500k,3500k_5000k,5000k_7000k,7000k_10000k,diatas_10000k',
                'kepemilikan_tabungan' => 'required|string|in:Ya,Tidak',
                'jumlah_makan' => 'required|string|in:Ya,Tidak',
                'kepemilikan_tanah' => 'required|string|in:Ya,Tidak',
                'kepemilikan_rumah' => 'required|string|in:Hak Milik,Sewa,Orang Tua,Saudara,Kerabat',
                'kondisi_rumah_dinding' => 'required|string|in:Tembok,Kayu,Papan,Geribik,Lainnya',
                'kondisi_rumah_lantai' => 'required|string|in:Keramik,Ubin,Marmer,Kayu,Tanah,Lainnya',
                'kepemilikan_kendaraan' => 'required|string|in:Sepeda,Motor,Mobil',
                'kepemilikan_elektronik' => 'required|string|in:Radio,Televisi,Handphone,Kulkas',
                'sumber_air_bersih' => 'required|string|in:Sumur,Sungai,PDAM,Lainnya',
                'jamban_limbah' => 'required|string|in:Sungai,Sepitank,Lainnya',
                'tempat_sampah' => 'required|string|in:TPS,Sungai,Pekarangan',
                'perokok' => 'required|string|in:Ya,Tidak',
                'konsumen_miras' => 'required|string|in:Ya,Tidak',
                'persediaan_p3k' => 'required|string|in:Ya,Tidak',
                'makan_buah_sayur' => 'required|string|in:Ya,Tidak',
                'solat_lima_waktu' => 'required|string|in:Lengkap,Kadang-kadang,Tidak Pernah',
                'membaca_alquran' => 'required|string|in:Lancar,Terbata-bata,Tidak Bisa',
                'majelis_taklim' => 'required|string|in:Rutin,Jarang,Tidak Pernah',
                'membaca_koran' => 'required|string|in:Selalu,Jarang,Tidak Pernah',
                'pengurus_organisasi' => 'required|string|in:Ya,Tidak',
                'pengurus_organisasi_sebagai' => 'nullable|string|required_if:pengurus_organisasi,Ya',
                'status_anak' => 'required|string|in:Yatim,Dhuafa,Non Dhuafa',
                'biaya_pendidikan_perbulan' => 'required|string',
                'bantuan_lembaga_formal_lain' => 'required|string|in:Ya,Tidak',
                'bantuan_lembaga_formal_lain_sebesar' => 'nullable|string|required_if:bantuan_lembaga_formal_lain,Ya',
                'kondisi_penerima_manfaat' => 'nullable|string',
                'petugas_survey' => 'nullable|string',
                'hasil_survey' => 'nullable|string|in:Layak,Tidak Layak', 
                'keterangan_hasil' => 'nullable|string|max:255'
            ]);
    
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            // Check if a survey already exists for this family
            $survey = Survey::where('id_keluarga', $id_keluarga)->first();
            
            // Prepare data to save/update
            $surveyData = $request->all();
            $surveyData['id_keluarga'] = $id_keluarga;
            
            // If survey already exists and previously had "Tidak Layak" result
            if ($survey && $survey->hasil_survey === 'Tidak Layak' && $request->hasil_survey === 'Layak') {
                $surveyData['hasil_survey'] = 'Tambah Kelayakan';
            }
            
            // Save or update survey data
            if ($survey) {
                $survey->update($surveyData);
            } else {
                $survey = Survey::create($surveyData);
            }
            
            // Update children's status_cpb to CPB if the survey result is "Layak"
            if ($survey->hasil_survey === 'Layak') {
                Anak::where('id_keluarga', $id_keluarga)
                    ->update(['status_cpb' => Anak::STATUS_CPB_CPB]);
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Data survey berhasil disimpan',
                'data' => $survey
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan data survey: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a survey record
     */
    public function destroy(Request $request, $id_keluarga)
    {
        try {
            $user = $request->user();
            $adminShelter = $user->adminShelter;
            
            if (!$adminShelter || !$adminShelter->id_shelter) {
                return response()->json([
                    'success' => false,
                    'message' => 'Admin shelter tidak ditemukan atau tidak terkait dengan shelter'
                ], 403);
            }
            
            $id_shelter = $adminShelter->id_shelter;
            
            // Check if keluarga exists and belongs to admin's shelter
            $keluarga = Keluarga::where('id_keluarga', $id_keluarga)
                ->where('id_shelter', $id_shelter)
                ->first();
                
            if (!$keluarga) {
                return response()->json([
                    'success' => false,
                    'message' => 'Keluarga tidak ditemukan atau tidak terkait dengan shelter Anda'
                ], 404);
            }
            
            // Delete the survey
            $deleted = Survey::where('id_keluarga', $id_keluarga)->delete();
            
            if (!$deleted) {
                return response()->json([
                    'success' => false,
                    'message' => 'Survey tidak ditemukan'
                ], 404);
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Survey berhasil dihapus'
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus survey: ' . $e->getMessage()
            ], 500);
        }
    }
}