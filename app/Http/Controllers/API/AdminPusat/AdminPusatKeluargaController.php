<?php

namespace App\Http\Controllers\API\AdminPusat;

use App\Http\Controllers\Controller;
use App\Models\Anak;
use App\Models\AnakPendidikan;
use App\Models\Ayah;
use App\Models\Bank;
use App\Models\Ibu;
use App\Models\Kacab;
use App\Models\Keluarga;
use App\Models\Shelter;
use App\Models\Wali;
use App\Models\Wilbin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class AdminPusatKeluargaController extends Controller
{
    /**
     * Display a paginated listing of families
     */
    public function index(Request $request)
    {
        // For AdminPusat, we don't filter by shelter like in AdminShelter
        $query = Keluarga::query();
        
        // Filter by search term (no_kk or kepala_keluarga)
        if ($request->has('search')) {
            $query->where(function($q) use ($request) {
                $q->where('no_kk', 'like', '%' . $request->search . '%')
                  ->orWhere('kepala_keluarga', 'like', '%' . $request->search . '%');
            });
        }

        // Filter by wilbin if requested
        if ($request->has('id_wilbin')) {
            $query->where('id_wilbin', $request->id_wilbin);
        }

        // Filter by kacab if requested
        if ($request->has('id_kacab')) {
            $query->where('id_kacab', $request->id_kacab);
        }
        
        // Filter by shelter if requested
        if ($request->has('id_shelter')) {
            $query->where('id_shelter', $request->id_shelter);
        }

        // Get the paginated results with relationships
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

    /**
     * Display the specified family details with all relationships
     */
    public function show($id)
    {
        // Find the family with all relationships
        $keluarga = Keluarga::with([
            'shelter', 
            'wilbin', 
            'kacab', 
            'bank',
            'ayah',
            'ibu',
            'wali',
            'surveys'
        ])->findOrFail($id);

        // Get all children in this family with their education details
        $anak = Anak::with('anakPendidikan')
                    ->where('id_keluarga', $id)
                    ->get();

        // Combine all data
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

    /**
     * Store a newly created family record with all related data
     */
    public function store(Request $request)
    {
        // Define validation rules
        $rules = [
            // Keluarga validation
            'no_kk' => 'required|string|max:20',
            'kepala_keluarga' => 'required|string|max:255',
            'status_ortu' => 'required|string|in:yatim,piatu,yatim piatu,dhuafa,non dhuafa',
            'id_kacab' => 'required|exists:kacab,id_kacab',
            'id_wilbin' => 'required|exists:wilbin,id_wilbin',
            'id_shelter' => 'required|exists:shelter,id_shelter',
            'id_bank' => 'nullable|exists:bank,id_bank',
            'no_rek' => 'nullable|string|max:255',
            'an_rek' => 'nullable|string|max:255',
            'no_tlp' => 'nullable|string|max:255',
            'an_tlp' => 'nullable|string|max:255',

            // AnakPendidikan validation
            'jenjang' => 'required|string|in:belum_sd,sd,smp,sma,perguruan_tinggi',
            'kelas' => 'nullable|string|max:255',
            'nama_sekolah' => 'nullable|string|max:255',
            'alamat_sekolah' => 'nullable|string|max:255',
            'jurusan' => 'nullable|string|max:255',
            'semester' => 'nullable|integer',
            'nama_pt' => 'nullable|string|max:255',
            'alamat_pt' => 'nullable|string|max:255',

            // Anak validation
            'nik_anak' => 'required|string|max:16',
            'anak_ke' => 'required|integer',
            'dari_bersaudara' => 'required|integer',
            'nick_name' => 'required|string|max:255',
            'full_name' => 'required|string|max:255',
            'agama' => 'required|string|in:Islam,Kristen,Budha,Hindu,Konghucu',
            'tempat_lahir' => 'required|string|max:255',
            'tanggal_lahir' => 'required|date',
            'jenis_kelamin' => 'required|string|in:Laki-laki,Perempuan',
            'tinggal_bersama' => 'required|string|in:Ayah,Ibu,Wali',
            'jenis_anak_binaan' => 'required|string|in:BPCB,NPB',
            'hafalan' => 'required|string|in:Tahfidz,Non-Tahfidz',
            'pelajaran_favorit' => 'nullable|string|max:255',
            'hobi' => 'nullable|string|max:255',
            'prestasi' => 'nullable|string|max:255',
            'jarak_rumah' => 'nullable|numeric',
            'transportasi' => 'required|string',
            'foto' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',

            // Ayah validation
            'nik_ayah' => 'nullable|string|max:16',
            'nama_ayah' => 'nullable|string|max:255',
            'agama_ayah' => 'nullable|string|in:Islam,Kristen,Budha,Hindu,Konghucu',
            'tempat_lahir_ayah' => 'nullable|string|max:255',
            'tanggal_lahir_ayah' => 'nullable|date',
            'alamat_ayah' => 'nullable|string',
            'id_prov_ayah' => 'nullable|string|max:2',
            'id_kab_ayah' => 'nullable|string|max:4',
            'id_kec_ayah' => 'nullable|string|max:6',
            'id_kel_ayah' => 'nullable|string|max:10',
            'penghasilan_ayah' => 'nullable|string',
            'tanggal_kematian_ayah' => 'nullable|date',
            'penyebab_kematian_ayah' => 'nullable|string|max:255',
            
            // Ibu validation
            'nik_ibu' => 'nullable|string|max:16',
            'nama_ibu' => 'nullable|string|max:255',
            'agama_ibu' => 'nullable|string|in:Islam,Kristen,Budha,Hindu,Konghucu',
            'tempat_lahir_ibu' => 'nullable|string|max:255',
            'tanggal_lahir_ibu' => 'nullable|date',
            'alamat_ibu' => 'nullable|string',
            'id_prov_ibu' => 'nullable|string|max:2',
            'id_kab_ibu' => 'nullable|string|max:4',
            'id_kec_ibu' => 'nullable|string|max:6',
            'id_kel_ibu' => 'nullable|string|max:10',
            'penghasilan_ibu' => 'nullable|string',
            'tanggal_kematian_ibu' => 'nullable|date',
            'penyebab_kematian_ibu' => 'nullable|string|max:255',
            
            // Wali validation
            'nik_wali' => 'nullable|string|max:16',
            'nama_wali' => 'nullable|string|max:255',
            'agama_wali' => 'nullable|string|in:Islam,Kristen,Budha,Hindu,Konghucu',
            'tempat_lahir_wali' => 'nullable|string|max:255',
            'tanggal_lahir_wali' => 'nullable|date',
            'alamat_wali' => 'nullable|string',
            'id_prov_wali' => 'nullable|string|max:2',
            'id_kab_wali' => 'nullable|string|max:4',
            'id_kec_wali' => 'nullable|string|max:6',
            'id_kel_wali' => 'nullable|string|max:10',
            'penghasilan_wali' => 'nullable|string',
            'hub_kerabat_wali' => 'nullable|string'
        ];
        
        // Create validator instance
        $validator = Validator::make($request->all(), $rules);
        
        // Check if validation fails
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Start transaction
        DB::beginTransaction();
        
        try {
            // Handle bank account and phone options
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
            
            // 1. Create Family (Keluarga)
            $keluarga = Keluarga::create([
                'no_kk' => $request->no_kk,
                'kepala_keluarga' => $request->kepala_keluarga,
                'status_ortu' => $request->status_ortu,
                'id_kacab' => $request->id_kacab,
                'id_wilbin' => $request->id_wilbin,
                'id_shelter' => $request->id_shelter,
                'id_bank' => $request->id_bank,
                'no_rek' => $request->no_rek,
                'an_rek' => $request->an_rek,
                'no_tlp' => $request->no_tlp,
                'an_tlp' => $request->an_tlp,
            ]);
            
            // 2. Create Ayah (Father)
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
            
            // 3. Create Ibu (Mother)
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
            
            // 4. Create Wali (Guardian)
            if ($request->nama_wali || $request->nik_wali) {
                $wali = Wali::create([
                    'id_keluarga' => $keluarga->id_keluarga,
                    'nik_wali' => $request->nik_wali,
                    'nama_wali' => $request->nama_wali,
                    'agama' => $request->agama_wali,
                    'tempat_lahir' => $request->tempat_lahir_wali,
                    'tanggal_lahir' => $request->tanggal_lahir_wali,
                    'alamat' => $request->alamat_wali,
                    'id_prov' => $request->id_prov_wali,
                    'id_kab' => $request->id_kab_wali,
                    'id_kec' => $request->id_kec_wali,
                    'id_kel' => $request->id_kel_wali,
                    'penghasilan' => $request->penghasilan_wali,
                    'hub_kerabat' => $request->hub_kerabat_wali,
                ]);
            }
            
            // 5. Create Anak Pendidikan (Child Education)
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
            
            // 6. Create Anak (Child)
            $anak = Anak::create([
                'id_keluarga' => $keluarga->id_keluarga,
                'id_anak_pend' => $pendidikan->id_anak_pend,
                'id_shelter' => $request->id_shelter,
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
                'jenis_anak_binaan' => $request->jenis_anak_binaan,
                'hafalan' => $request->hafalan,
                'pelajaran_favorit' => $request->pelajaran_favorit,
                'hobi' => $request->hobi,
                'prestasi' => $request->prestasi,
                'jarak_rumah' => $request->jarak_rumah,
                'transportasi' => $request->transportasi,
                'status_validasi' => 'non-aktif', // Default status
            ]);
            
            // Upload foto if present
            if ($request->hasFile('foto')) {
                $folderPath = 'Anak/' . $anak->id_anak;
                $fileName = $request->file('foto')->getClientOriginalName();
                $request->file('foto')->storeAs($folderPath, $fileName, 'public');
                
                // Update anak with photo filename
                $anak->update(['foto' => $fileName]);
            }
            
            // Commit transaction
            DB::commit();
            
            // Return success response
            return response()->json([
                'success' => true,
                'message' => 'Keluarga dan Anak berhasil ditambahkan',
                'data' => [
                    'keluarga' => $keluarga,
                    'anak' => $anak
                ]
            ], 201);
            
        } catch (\Exception $e) {
            // Rollback transaction on error
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan data: ' . $e->getMessage(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    /**
     * Update the specified family and related records
     */
    public function update(Request $request, $id)
    {
        // Find the family record
        $keluarga = Keluarga::findOrFail($id);
        
        // Define validation rules
        $rules = [
            // Keluarga validation
            'no_kk' => 'sometimes|required|string|max:20',
            'kepala_keluarga' => 'sometimes|required|string|max:255',
            'status_ortu' => 'sometimes|required|string|in:yatim,piatu,yatim piatu,dhuafa,non dhuafa',
            'id_kacab' => 'sometimes|required|exists:kacab,id_kacab',
            'id_wilbin' => 'sometimes|required|exists:wilbin,id_wilbin',
            'id_shelter' => 'sometimes|required|exists:shelter,id_shelter',
            'id_bank' => 'nullable|exists:bank,id_bank',
            'no_rek' => 'nullable|string|max:255',
            'an_rek' => 'nullable|string|max:255',
            'no_tlp' => 'nullable|string|max:255',
            'an_tlp' => 'nullable|string|max:255',
        ];
        
        // Create validator instance
        $validator = Validator::make($request->all(), $rules);
        
        // Check if validation fails
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Start transaction
        DB::beginTransaction();
        
        try {
            // Update Keluarga data
            $keluarga->update($request->all());
            
            // Update Ayah (if provided)
            if ($request->has('ayah')) {
                $ayah = Ayah::where('id_keluarga', $id)->first();
                if ($ayah) {
                    $ayah->update($request->ayah);
                }
            }
            
            // Update Ibu (if provided)
            if ($request->has('ibu')) {
                $ibu = Ibu::where('id_keluarga', $id)->first();
                if ($ibu) {
                    $ibu->update($request->ibu);
                }
            }
            
            // Update Wali (if provided)
            if ($request->has('wali')) {
                $wali = Wali::where('id_keluarga', $id)->first();
                if ($wali) {
                    $wali->update($request->wali);
                }
            }
            
            // Commit transaction
            DB::commit();
            
            // Get updated family data with relationships
            $updatedKeluarga = Keluarga::with(['ayah', 'ibu', 'wali', 'kacab', 'wilbin', 'shelter', 'bank'])
                                      ->findOrFail($id);
            
            // Return success response
            return response()->json([
                'success' => true,
                'message' => 'Data keluarga berhasil diperbarui',
                'data' => $updatedKeluarga
            ], 200);
            
        } catch (\Exception $e) {
            // Rollback transaction on error
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui data: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified family
     */
    public function destroy($id)
    {
        // Find the family
        $keluarga = Keluarga::findOrFail($id);
        
        try {
            DB::beginTransaction();
            
            // First find children to delete their photos
            $anak = Anak::where('id_keluarga', $id)->get();
            
            // Delete child photos from storage
            foreach ($anak as $child) {
                if ($child->foto) {
                    $folderPath = 'Anak/' . $child->id_anak;
                    Storage::disk('public')->deleteDirectory($folderPath);
                }
            }
            
            // Now delete the family record (which should cascade to related records)
            $keluarga->delete();
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Keluarga berhasil dihapus'
            ], 200);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus keluarga: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get dropdown data for forms
     */
    public function getDropdownData()
    {
        try {
            $data = [
                'kacab' => Kacab::all(['id_kacab', 'nama_kacab']),
                'bank' => Bank::all(['id_bank', 'nama_bank']),
                'shelter' => Shelter::all(['id_shelter', 'nama_shelter']),
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
    
    /**
     * Get wilbin options based on kacab selection
     */
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
    
    /**
     * Get shelter options based on wilbin selection
     */
    public function getShelterByWilbin(Request $request, $id_wilbin)
    {
        try {
            $shelter = Shelter::where('id_wilbin', $id_wilbin)
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