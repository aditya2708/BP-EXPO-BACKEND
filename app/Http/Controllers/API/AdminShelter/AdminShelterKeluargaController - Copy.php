<?php

namespace App\Http\Controllers\API\AdminShelter;

use App\Http\Controllers\Controller;
use App\Models\Anak;
use App\Models\AnakPendidikan;
use App\Models\Ayah;
use App\Models\Bank;
use App\Models\Ibu;
use App\Models\Kacab;
use App\Models\Keluarga;
use App\Models\Shelter;
use App\Models\Survey;
use App\Models\Wali;
use App\Models\Wilbin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class AdminShelterKeluargaController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        
        if (!$user->adminShelter || !$user->adminShelter->shelter) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }
        
        $shelterId = $user->adminShelter->id_shelter;
        
        $query = Keluarga::query();
        
        $query->where('id_shelter', $shelterId);

        if ($request->has('search')) {
            $query->where(function($q) use ($request) {
                $q->where('no_kk', 'like', '%' . $request->search . '%')
                  ->orWhere('kepala_keluarga', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->has('id_wilbin')) {
            $query->where('id_wilbin', $request->id_wilbin);
        }

        if ($request->has('id_kacab')) {
            $query->where('id_kacab', $request->id_kacab);
        }

        $keluarga = $query->with(['shelter', 'wilbin', 'kacab', 'bank'])
                          ->latest()
                          ->paginate($request->per_page ?? 10);

        return response()->json([
            'success' => true,
            'message' => 'Daftar Keluarga',
            'data' => $keluarga->items(),
            'pagination' => [
                'total' => $keluarga->total(),
                'per_page' => $keluarga->perPage(),
                'current_page' => $keluarga->currentPage(),
                'last_page' => $keluarga->lastPage(),
                'from' => $keluarga->firstItem(),
                'to' => $keluarga->lastItem()
            ]
        ], 200);
    }

    public function show($id)
    {
        $user = Auth::user();
        
        if (!$user->adminShelter || !$user->adminShelter->shelter) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }
        
        $shelterId = $user->adminShelter->id_shelter;
        
        $keluarga = Keluarga::where('id_shelter', $shelterId)
            ->with([
                'shelter', 
                'wilbin', 
                'kacab', 
                'bank',
                'ayah',
                'ibu',
                'wali',
                'surveys'
            ])->findOrFail($id);

        $anak = Anak::with('anakPendidikan')
                    ->where('id_keluarga', $id)
                    ->get();

        $data = [
            'keluarga' => $keluarga,
            'anak' => $anak
        ];

        return response()->json([
            'success' => true,
            'message' => 'Detail Keluarga',
            'data' => $data
        ], 200);
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        
        if (!$user->adminShelter || !$user->adminShelter->shelter) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }
        
        $shelterId = $user->adminShelter->id_shelter;
        
        // Base validation rules
        $rules = [
            // Family data - always required
            'no_kk' => 'required|regex:/^[0-9]{16}$/',
            'kepala_keluarga' => 'required|string|max:255',
            'status_ortu' => 'required|string|in:yatim,piatu,yatim piatu,dhuafa,non dhuafa',
            
            // Bank/Phone - conditional based on choice
            'id_bank' => 'nullable|exists:bank,id_bank',
            'no_rek' => 'nullable|string|max:255',
            'an_rek' => 'nullable|string|max:255',
            'no_tlp' => 'nullable|string|max:255',
            'an_tlp' => 'nullable|string|max:255',

            // Education - conditional based on jenjang
            'jenjang' => 'required|string|in:belum_sd,sd,smp,sma,perguruan_tinggi',
            'kelas' => 'nullable|string|max:255',
            'nama_sekolah' => 'nullable|string|max:255',
            'alamat_sekolah' => 'nullable|string|max:255',
            'jurusan' => 'nullable|string|max:255',
            'semester' => 'nullable|integer',
            'nama_pt' => 'nullable|string|max:255',
            'alamat_pt' => 'nullable|string|max:255',

            // Child data - ALL required (no conditionals)
            'nik_anak' => 'required|regex:/^[0-9]{16}$/',
            'anak_ke' => 'required|integer',
            'dari_bersaudara' => 'required|integer',
            'nick_name' => 'required|string|max:255',
            'full_name' => 'required|string|max:255',
            'agama' => 'required|string|in:Islam,Kristen,Katolik,Buddha,Hindu,Konghucu',
            'tempat_lahir' => 'required|string|max:255',
            'tanggal_lahir' => 'required|date',
            'jenis_kelamin' => 'required|string|in:Laki-laki,Perempuan',
            'tinggal_bersama' => 'required|string|in:Ayah,Ibu,Ayah dan Ibu,Wali',
            'hafalan' => 'required|string|in:Tahfidz,Non-Tahfidz',
            'pelajaran_favorit' => 'required|string|max:255',
            'hobi' => 'required|string|max:255',
            'prestasi' => 'required|string|max:255',
            'jarak_rumah' => 'required|numeric',
            'transportasi' => 'required|string',
            'foto' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',

            // Parent data - conditional validation based on status_ortu
            'nama_ayah' => 'required|string|max:255',
            'nama_ibu' => 'required|string|max:255',
            'tanggal_kematian_ayah' => 'nullable|date|required_if:status_ortu,yatim,yatim piatu',
            'penyebab_kematian_ayah' => 'nullable|string|max:255|required_if:status_ortu,yatim,yatim piatu',
            'tanggal_kematian_ibu' => 'nullable|date|required_if:status_ortu,piatu,yatim piatu',
            'penyebab_kematian_ibu' => 'nullable|string|max:255|required_if:status_ortu,piatu,yatim piatu',

            // Survey fields - ALL required
            'pekerjaan_kepala_keluarga' => 'required|string|max:255',
            'penghasilan' => 'required|string|max:255',
            'pendidikan_kepala_keluarga' => 'required|string|max:255',
            'jumlah_tanggungan' => 'required|integer',
            'kepemilikan_tabungan' => 'required|string|in:Ada,Tidak Ada',
            'jumlah_makan' => 'required|integer',
            'kepemilikan_tanah' => 'required|string|in:Milik Sendiri,Kontrak,Menumpang,Lainnya',
            'kepemilikan_rumah' => 'required|string|in:Milik Sendiri,Kontrak,Menumpang,Lainnya',
            'kondisi_rumah_dinding' => 'required|string|max:255',
            'kondisi_rumah_lantai' => 'required|string|max:255',
            'kepemilikan_kendaraan' => 'required|string|max:255',
            'kepemilikan_elektronik' => 'required|string|max:255',
            'sumber_air_bersih' => 'required|string|max:255',
            'jamban_limbah' => 'required|string|max:255',
            'tempat_sampah' => 'required|string|max:255',
            'perokok' => 'required|string|in:Ya,Tidak',
            'konsumen_miras' => 'required|string|in:Ya,Tidak',
            'persediaan_p3k' => 'required|string|in:Ada,Tidak Ada',
            'makan_buah_sayur' => 'required|string|in:Selalu,Kadang-kadang,Tidak Pernah',
            'solat_lima_waktu' => 'required|string|in:Selalu,Kadang-kadang,Tidak Pernah',
            'membaca_alquran' => 'required|string|in:Selalu,Kadang-kadang,Tidak Pernah',
            'majelis_taklim' => 'required|string|in:Aktif,Tidak Aktif',
            'membaca_koran' => 'required|string|in:Selalu,Kadang-kadang,Tidak Pernah',
            'pengurus_organisasi' => 'required|string|in:Ya,Tidak',
            'kondisi_fisik_anak' => 'required|string|in:Normal,Disabilitas',
            'biaya_pendidikan_perbulan' => 'required|numeric',
            'bantuan_lembaga_formal_lain' => 'required|string|in:Ya,Tidak',
            'kondisi_penerima_manfaat' => 'required|string|max:255',
            'kepribadian_anak' => 'required|string|max:255',
        ];

        // Add conditional validations based on status_ortu and other choices
        $statusOrtu = $request->input('status_ortu');
        
        // Parent fields based on status
        if (in_array($statusOrtu, ['piatu', 'dhuafa', 'non dhuafa'])) {
            // Father is alive - require all father fields
            $rules['nik_ayah'] = 'required|regex:/^[0-9]{16}$/';
            $rules['agama_ayah'] = 'required|string|in:Islam,Kristen,Katolik,Buddha,Hindu,Konghucu';
            $rules['tempat_lahir_ayah'] = 'required|string|max:255';
            $rules['tanggal_lahir_ayah'] = 'required|date';
            $rules['alamat_ayah'] = 'required|string';
            $rules['id_prov_ayah'] = 'required|string|max:2';
            $rules['id_kab_ayah'] = 'required|string|max:4';
            $rules['id_kec_ayah'] = 'required|string|max:6';
            $rules['id_kel_ayah'] = 'required|string|max:10';
            $rules['penghasilan_ayah'] = 'required|string';
        }
        
        if (in_array($statusOrtu, ['yatim', 'dhuafa', 'non dhuafa'])) {
            // Mother is alive - require all mother fields
            $rules['nik_ibu'] = 'required|regex:/^[0-9]{16}$/';
            $rules['agama_ibu'] = 'required|string|in:Islam,Kristen,Katolik,Buddha,Hindu,Konghucu';
            $rules['tempat_lahir_ibu'] = 'required|string|max:255';
            $rules['tanggal_lahir_ibu'] = 'required|date';
            $rules['alamat_ibu'] = 'required|string';
            $rules['id_prov_ibu'] = 'required|string|max:2';
            $rules['id_kab_ibu'] = 'required|string|max:4';
            $rules['id_kec_ibu'] = 'required|string|max:6';
            $rules['id_kel_ibu'] = 'required|string|max:10';
            $rules['penghasilan_ibu'] = 'required|string';
        }
        
        // Guardian fields for yatim piatu
        if ($statusOrtu === 'yatim piatu') {
            $rules['nik_wali'] = 'required|regex:/^[0-9]{16}$/';
            $rules['nama_wali'] = 'required|string|max:255';
            $rules['agama_wali'] = 'required|string|in:Islam,Kristen,Katolik,Buddha,Hindu,Konghucu';
            $rules['tempat_lahir_wali'] = 'required|string|max:255';
            $rules['tanggal_lahir_wali'] = 'required|date';
            $rules['alamat_wali'] = 'required|string';
            $rules['penghasilan_wali'] = 'required|string';
            $rules['hub_kerabat_wali'] = 'required|string';
        }
        
        // Bank fields if bank is chosen
        if ($request->input('bank_choice') === 'yes') {
            $rules['id_bank'] = 'required|exists:bank,id_bank';
            $rules['no_rek'] = 'required|string|max:255';
            $rules['an_rek'] = 'required|string|max:255';
        }
        
        // Phone fields if phone is chosen
        if ($request->input('telp_choice') === 'yes') {
            $rules['no_tlp'] = 'required|string|max:255';
            $rules['an_tlp'] = 'required|string|max:255';
        }
        
        // Education fields based on jenjang
        $jenjang = $request->input('jenjang');
        if (in_array($jenjang, ['sd', 'smp'])) {
            $rules['kelas'] = 'required|string|max:255';
            $rules['nama_sekolah'] = 'required|string|max:255';
            $rules['alamat_sekolah'] = 'required|string|max:255';
        } elseif ($jenjang === 'sma') {
            $rules['kelas'] = 'required|string|max:255';
            $rules['nama_sekolah'] = 'required|string|max:255';
            $rules['alamat_sekolah'] = 'required|string|max:255';
            $rules['jurusan'] = 'required|string|max:255';
        } elseif ($jenjang === 'perguruan_tinggi') {
            $rules['semester'] = 'required|integer';
            $rules['jurusan'] = 'required|string|max:255';
            $rules['nama_pt'] = 'required|string|max:255';
            $rules['alamat_pt'] = 'required|string|max:255';
        }
        
        // Conditional survey fields
        if ($request->input('kondisi_fisik_anak') === 'Disabilitas') {
            $rules['keterangan_disabilitas'] = 'required|string';
        }
        
        if ($request->input('bantuan_lembaga_formal_lain') === 'Ya') {
            $rules['bantuan_lembaga_formal_lain_sebesar'] = 'required|numeric';
        }
        
        if ($request->input('pengurus_organisasi') === 'Ya') {
            $rules['pengurus_organisasi_sebagai'] = 'required|string|max:255';
        }
        
        $validator = Validator::make($request->all(), $rules);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Error',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        
        try {
            if ($request->has('bank_choice') && $request->bank_choice == 'no') {
                $request->merge([
                    'id_bank' => null,
                    'no_rek' => null,
                    'an_rek' => null
                ]);
            }
            
            if ($request->has('telp_choice') && $request->telp_choice == 'no') {
                $request->merge([
                    'no_tlp' => null,
                    'an_tlp' => null
                ]);
            }
            
            $keluarga = Keluarga::create([
                'no_kk' => $request->no_kk,
                'kepala_keluarga' => $request->kepala_keluarga,
                'status_ortu' => $request->status_ortu,
                'id_kacab' => $user->adminShelter->id_kacab,
                'id_wilbin' => $user->adminShelter->id_wilbin,
                'id_shelter' => $shelterId,
                'id_bank' => $request->id_bank,
                'no_rek' => $request->no_rek,
                'an_rek' => $request->an_rek,
                'no_tlp' => $request->no_tlp,
                'an_tlp' => $request->an_tlp,
            ]);
            
            // Always create father record
            $ayah = Ayah::create([
                'id_keluarga' => $keluarga->id_keluarga,
                'nik_ayah' => $request->nik_ayah,
                'nama_ayah' => $request->nama_ayah,
                'agama' => $request->agama_ayah,
                'tempat_lahir' => $request->tempat_lahir_ayah,
                'tanggal_lahir' => $request->tanggal_lahir_ayah,
                'alamat' => $request->alamat_ayah,
                'id_prov' => $request->id_prov_ayah,
                'id_kab' => $request->id_kab_ayah,
                'id_kec' => $request->id_kec_ayah,
                'id_kel' => $request->id_kel_ayah,
                'penghasilan' => $request->penghasilan_ayah,
                'tanggal_kematian' => $request->tanggal_kematian_ayah,
                'penyebab_kematian' => $request->penyebab_kematian_ayah,
            ]);
            
            // Always create mother record
            $ibu = Ibu::create([
                'id_keluarga' => $keluarga->id_keluarga,
                'nik_ibu' => $request->nik_ibu,
                'nama_ibu' => $request->nama_ibu,
                'agama' => $request->agama_ibu,
                'tempat_lahir' => $request->tempat_lahir_ibu,
                'tanggal_lahir' => $request->tanggal_lahir_ibu,
                'alamat' => $request->alamat_ibu,
                'id_prov' => $request->id_prov_ibu,
                'id_kab' => $request->id_kab_ibu,
                'id_kec' => $request->id_kec_ibu,
                'id_kel' => $request->id_kel_ibu,
                'penghasilan' => $request->penghasilan_ibu,
                'tanggal_kematian' => $request->tanggal_kematian_ibu,
                'penyebab_kematian' => $request->penyebab_kematian_ibu,
            ]);
            
            if ($request->nik_wali || $request->nama_wali || $request->status_ortu === 'yatim piatu') {
                $wali = Wali::create([
                    'id_keluarga' => $keluarga->id_keluarga,
                    'nik_wali' => $request->nik_wali,
                    'nama_wali' => $request->nama_wali,
                    'agama' => $request->agama_wali,
                    'tempat_lahir' => $request->tempat_lahir_wali,
                    'tanggal_lahir' => $request->tanggal_lahir_wali,
                    'alamat' => $request->alamat_wali,
                    'penghasilan' => $request->penghasilan_wali,
                    'hub_kerabat' => $request->hub_kerabat_wali,
                ]);
            }
            
            $pendidikan = AnakPendidikan::create([
                'id_keluarga' => $keluarga->id_keluarga,
                'jenjang' => $request->jenjang,
                'kelas' => $request->kelas,
                'nama_sekolah' => $request->nama_sekolah,
                'alamat_sekolah' => $request->alamat_sekolah,
                'jurusan' => $request->jurusan,
                'semester' => $request->semester,
                'nama_pt' => $request->nama_pt,
                'alamat_pt' => $request->alamat_pt,
            ]);

            $survey = Survey::create([
                'id_keluarga' => $keluarga->id_keluarga,
                'pekerjaan_kepala_keluarga' => $request->pekerjaan_kepala_keluarga,
                'penghasilan' => $request->penghasilan,
                'pendidikan_kepala_keluarga' => $request->pendidikan_kepala_keluarga,
                'jumlah_tanggungan' => $request->jumlah_tanggungan,
                'kepemilikan_tabungan' => $request->kepemilikan_tabungan,
                'jumlah_makan' => $request->jumlah_makan,
                'kepemilikan_tanah' => $request->kepemilikan_tanah,
                'kepemilikan_rumah' => $request->kepemilikan_rumah,
                'kondisi_rumah_dinding' => $request->kondisi_rumah_dinding,
                'kondisi_rumah_lantai' => $request->kondisi_rumah_lantai,
                'kepemilikan_kendaraan' => $request->kepemilikan_kendaraan,
                'kepemilikan_elektronik' => $request->kepemilikan_elektronik,
                'sumber_air_bersih' => $request->sumber_air_bersih,
                'jamban_limbah' => $request->jamban_limbah,
                'tempat_sampah' => $request->tempat_sampah,
                'perokok' => $request->perokok,
                'konsumen_miras' => $request->konsumen_miras,
                'persediaan_p3k' => $request->persediaan_p3k,
                'makan_buah_sayur' => $request->makan_buah_sayur,
                'solat_lima_waktu' => $request->solat_lima_waktu,
                'membaca_alquran' => $request->membaca_alquran,
                'majelis_taklim' => $request->majelis_taklim,
                'membaca_koran' => $request->membaca_koran,
                'pengurus_organisasi' => $request->pengurus_organisasi,
                'pengurus_organisasi_sebagai' => $request->pengurus_organisasi_sebagai,
                'kepribadian_anak' => $request->kepribadian_anak,
                'kondisi_fisik_anak' => $request->kondisi_fisik_anak,
                'keterangan_disabilitas' => $request->keterangan_disabilitas,
                'biaya_pendidikan_perbulan' => $request->biaya_pendidikan_perbulan,
                'bantuan_lembaga_formal_lain' => $request->bantuan_lembaga_formal_lain,
                'bantuan_lembaga_formal_lain_sebesar' => $request->bantuan_lembaga_formal_lain_sebesar,
                'kondisi_penerima_manfaat' => $request->kondisi_penerima_manfaat,
                'tanggal_survey' => now(),
                'petugas_survey' => $user->name,
                'hasil_survey' => 'pending',
                'keterangan_hasil' => 'Menunggu persetujuan admin cabang'
            ]);
            
            $anak = Anak::create([
                'id_keluarga' => $keluarga->id_keluarga,
                'id_anak_pend' => $pendidikan->id_anak_pend,
                'id_shelter' => $shelterId,
                'nik_anak' => $request->nik_anak,
                'anak_ke' => $request->anak_ke,
                'dari_bersaudara' => $request->dari_bersaudara,
                'nick_name' => $request->nick_name,
                'full_name' => $request->full_name,
                'agama' => $request->agama,
                'tempat_lahir' => $request->tempat_lahir,
                'tanggal_lahir' => $request->tanggal_lahir,
                'jenis_kelamin' => $request->jenis_kelamin,
                'tinggal_bersama' => $request->tinggal_bersama,
                'hafalan' => $request->hafalan,
                'pelajaran_favorit' => $request->pelajaran_favorit,
                'hobi' => $request->hobi,
                'prestasi' => $request->prestasi,
                'jarak_rumah' => $request->jarak_rumah,
                'transportasi' => $request->transportasi,
                'status_validasi' => 'aktif',
                'status_cpb' => 'BCPB',
            ]);
            
            if ($request->hasFile('foto')) {
                $folderPath = 'Anak/' . $anak->id_anak;
                $fileName = $request->file('foto')->getClientOriginalName();
                $request->file('foto')->storeAs($folderPath, $fileName, 'public');
                
                $anak->update(['foto' => $fileName]);
            }
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Keluarga dan Anak berhasil ditambahkan',
                'data' => [
                    'keluarga' => $keluarga,
                    'anak' => $anak,
                    'survey' => $survey
                ]
            ], 201);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan data: ' . $e->getMessage(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $user = Auth::user();
        
        if (!$user->adminShelter || !$user->adminShelter->shelter) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }
        
        $shelterId = $user->adminShelter->id_shelter;
        
        $keluarga = Keluarga::where('id_shelter', $shelterId)->findOrFail($id);
        
        $rules = [
            'no_kk' => 'sometimes|required|regex:/^[0-9]{16}$/',
            'kepala_keluarga' => 'sometimes|required|string|max:255',
            'status_ortu' => 'sometimes|required|string|in:yatim,piatu,yatim piatu,dhuafa,non dhuafa',
            'id_bank' => 'nullable|exists:bank,id_bank',
            'no_rek' => 'nullable|string|max:255',
            'an_rek' => 'nullable|string|max:255',
            'no_tlp' => 'nullable|string|max:255',
            'an_tlp' => 'nullable|string|max:255',
        ];
        
        $validator = Validator::make($request->all(), $rules);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Error',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        
        try {
            $updateData = $request->only([
                'no_kk', 'kepala_keluarga', 'status_ortu', 'id_bank', 'no_rek', 
                'an_rek', 'no_tlp', 'an_tlp'
            ]);
            
            // Auto-populate id_kacab and id_wilbin from admin shelter profile
            $updateData['id_kacab'] = $user->adminShelter->id_kacab;
            $updateData['id_wilbin'] = $user->adminShelter->id_wilbin;
            
            $keluarga->update($updateData);
            
            if ($request->has('nik_ayah') || $request->has('nama_ayah')) {
                $ayah = Ayah::where('id_keluarga', $id)->first();
                if ($ayah) {
                    $ayah->update([
                        'nik_ayah' => $request->nik_ayah,
                        'nama_ayah' => $request->nama_ayah,
                        'agama' => $request->agama_ayah,
                        'tempat_lahir' => $request->tempat_lahir_ayah,
                        'tanggal_lahir' => $request->tanggal_lahir_ayah,
                        'alamat' => $request->alamat_ayah,
                        'id_prov' => $request->id_prov_ayah,
                        'id_kab' => $request->id_kab_ayah,
                        'id_kec' => $request->id_kec_ayah,
                        'id_kel' => $request->id_kel_ayah,
                        'penghasilan' => $request->penghasilan_ayah,
                        'tanggal_kematian' => $request->tanggal_kematian_ayah,
                        'penyebab_kematian' => $request->penyebab_kematian_ayah,
                    ]);
                }
            }
            
            if ($request->has('nik_ibu') || $request->has('nama_ibu')) {
                $ibu = Ibu::where('id_keluarga', $id)->first();
                if ($ibu) {
                    $ibu->update([
                        'nik_ibu' => $request->nik_ibu,
                        'nama_ibu' => $request->nama_ibu,
                        'agama' => $request->agama_ibu,
                        'tempat_lahir' => $request->tempat_lahir_ibu,
                        'tanggal_lahir' => $request->tanggal_lahir_ibu,
                        'alamat' => $request->alamat_ibu,
                        'id_prov' => $request->id_prov_ibu,
                        'id_kab' => $request->id_kab_ibu,
                        'id_kec' => $request->id_kec_ibu,
                        'id_kel' => $request->id_kel_ibu,
                        'penghasilan' => $request->penghasilan_ibu,
                        'tanggal_kematian' => $request->tanggal_kematian_ibu,
                        'penyebab_kematian' => $request->penyebab_kematian_ibu,
                    ]);
                }
            }
            
            if ($request->has('nik_wali') || $request->has('nama_wali')) {
                $wali = Wali::where('id_keluarga', $id)->first();
                if ($wali) {
                    $wali->update([
                        'nik_wali' => $request->nik_wali,
                        'nama_wali' => $request->nama_wali,
                        'agama' => $request->agama_wali,
                        'tempat_lahir' => $request->tempat_lahir_wali,
                        'tanggal_lahir' => $request->tanggal_lahir_wali,
                        'alamat' => $request->alamat_wali,
                        'penghasilan' => $request->penghasilan_wali,
                        'hub_kerabat' => $request->hub_kerabat_wali,
                    ]);
                }
            }
            
            DB::commit();
            
            $updatedKeluarga = Keluarga::with(['ayah', 'ibu', 'wali', 'kacab', 'wilbin', 'shelter', 'bank'])
                                      ->findOrFail($id);
            
            return response()->json([
                'success' => true,
                'message' => 'Data keluarga berhasil diperbarui',
                'data' => $updatedKeluarga
            ], 200);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui data: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        $user = Auth::user();
        
        if (!$user->adminShelter || !$user->adminShelter->shelter) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }
        
        $shelterId = $user->adminShelter->id_shelter;
        
        $keluarga = Keluarga::where('id_shelter', $shelterId)->findOrFail($id);
        
        try {
            DB::beginTransaction();
            
            // Get active children count
            $anakAktif = Anak::where('id_keluarga', $id)
                            ->where('status_validasi', 'aktif')
                            ->get();
            
            $anakTidakAktif = Anak::where('id_keluarga', $id)
                                ->where('status_validasi', '!=', 'aktif')
                                ->get();
            
            $totalAnak = $anakAktif->count() + $anakTidakAktif->count();
            
            // Prepare response data with children information
            $responseData = [
                'keluarga' => $keluarga,
                'children_info' => [
                    'total_children' => $totalAnak,
                    'active_children' => $anakAktif->count(),
                    'inactive_children' => $anakTidakAktif->count(),
                    'active_children_list' => $anakAktif->map(function($child) {
                        return [
                            'id_anak' => $child->id_anak,
                            'full_name' => $child->full_name,
                            'nick_name' => $child->nick_name,
                            'status_validasi' => $child->status_validasi
                        ];
                    }),
                    'inactive_children_list' => $anakTidakAktif->map(function($child) {
                        return [
                            'id_anak' => $child->id_anak,
                            'full_name' => $child->full_name,
                            'nick_name' => $child->nick_name,
                            'status_validasi' => $child->status_validasi
                        ];
                    })
                ]
            ];
            
            // If there are active children, warn user but allow deletion with options
            if ($anakAktif->count() > 0) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Keluarga memiliki anak aktif yang terdaftar',
                    'code' => 'HAS_ACTIVE_CHILDREN',
                    'data' => $responseData
                ], 422);
            }
            
            // Proceed with soft delete
            $keluarga->delete();
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Keluarga berhasil dihapus',
                'data' => $responseData
            ], 200);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus keluarga: ' . $e->getMessage()
            ], 500);
        }
    }

    public function forceDestroy($id)
    {
        $user = Auth::user();
        
        if (!$user->adminShelter || !$user->adminShelter->shelter) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }
        
        $shelterId = $user->adminShelter->id_shelter;
        
        $keluarga = Keluarga::where('id_shelter', $shelterId)->findOrFail($id);
        
        try {
            DB::beginTransaction();
            
            // Get all children and update their status to 'tanpa_keluarga'
            $allChildren = Anak::where('id_keluarga', $id)->get();
            
            foreach ($allChildren as $child) {
                $child->update([
                    'status_keluarga' => 'tanpa_keluarga',
                    'keterangan_keluarga' => 'Keluarga dihapus pada ' . now()->format('Y-m-d H:i:s') . ' oleh ' . $user->name
                ]);
            }
            
            // Soft delete the family
            $keluarga->delete();
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Keluarga berhasil dihapus dan ' . $allChildren->count() . ' anak diubah statusnya menjadi tanpa keluarga',
                'data' => [
                    'affected_children' => $allChildren->count(),
                    'children_list' => $allChildren->map(function($child) {
                        return [
                            'id_anak' => $child->id_anak,
                            'full_name' => $child->full_name,
                            'nick_name' => $child->nick_name,
                            'status_validasi' => $child->status_validasi
                        ];
                    })
                ]
            ], 200);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus keluarga: ' . $e->getMessage()
            ], 500);
        }
    }
    
    public function getDropdownData()
    {
        try {
            $data = [
                'kacab' => Kacab::all(['id_kacab', 'nama_kacab']),
                'bank' => Bank::all(['id_bank', 'nama_bank']),
            ];
            
            return response()->json([
                'success' => true,
                'data' => $data
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching dropdown data: ' . $e->getMessage()
            ], 500);
        } 
    }
    
    public function getWilbinByKacab(Request $request, $id_kacab)
    {
        try {
            $wilbin = Wilbin::where('id_kacab', $id_kacab)
                            ->get(['id_wilbin', 'nama_wilbin']);
            
            return response()->json([
                'success' => true,
                'data' => $wilbin
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching wilbin data: ' . $e->getMessage()
            ], 500);
        }
    }
    
    public function getShelterByWilbin(Request $request, $id_wilbin)
    {
        $user = Auth::user();
        
        if (!$user->adminShelter || !$user->adminShelter->shelter) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }
        
        $shelterId = $user->adminShelter->id_shelter;
        
        try {
            $shelter = Shelter::where('id_wilbin', $id_wilbin)
                              ->where('id_shelter', $shelterId)
                              ->get(['id_shelter', 'nama_shelter']);
            
            return response()->json([
                'success' => true,
                'data' => $shelter
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching shelter data: ' . $e->getMessage()
            ], 500);
        }
    }
}