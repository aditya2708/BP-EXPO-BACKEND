<?php

namespace App\Http\Requests\AdminShelter;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class UpdateKeluargaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = Auth::user();
        return $user && $user->adminShelter && $user->adminShelter->shelter;
    }

    public function rules(): array
    {
        $data = $this->all();
        
        $rules = [
            // Family data
            'no_kk' => 'sometimes|required|regex:/^[0-9]{16}$/',
            'kepala_keluarga' => 'sometimes|required|string|max:255',
            'status_ortu' => 'sometimes|required|string|in:yatim,piatu,yatim piatu,dhuafa,non dhuafa',
            'id_bank' => 'nullable|exists:bank,id_bank',
            'no_rek' => 'nullable|string|max:255',
            'an_rek' => 'nullable|string|max:255',
            'no_tlp' => 'nullable|string|max:255',
            'an_tlp' => 'nullable|string|max:255',

            // Child data
            'nik_anak' => 'sometimes|required|regex:/^[0-9]{16}$/',
            'anak_ke' => 'sometimes|required|integer',
            'dari_bersaudara' => 'sometimes|required|integer',
            'nick_name' => 'sometimes|required|string|max:255',
            'full_name' => 'sometimes|required|string|max:255',
            'agama' => 'sometimes|required|string|in:Islam,Kristen,Katolik,Buddha,Hindu,Konghucu',
            'tempat_lahir' => 'sometimes|required|string|max:255',
            'tanggal_lahir' => 'sometimes|required|date',
            'jenis_kelamin' => 'sometimes|required|string|in:Laki-laki,Perempuan',
            'tinggal_bersama' => 'sometimes|required|string|in:Ayah,Ibu,Ayah dan Ibu,Wali',
            'hafalan' => 'sometimes|required|string|in:Tahfidz,Non-Tahfidz',
            'pelajaran_favorit' => 'sometimes|required|string|max:255',
            'hobi' => 'sometimes|required|string|max:255',
            'prestasi' => 'sometimes|required|string|max:255',
            'jarak_rumah' => 'sometimes|required|numeric',
            'transportasi' => 'sometimes|required|string',
            'foto' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',

            // Parent data
            'nama_ayah' => 'sometimes|required|string|max:255',
            'nama_ibu' => 'sometimes|required|string|max:255',
            'nik_ayah' => 'nullable|regex:/^[0-9]{16}$/',
            'nik_ibu' => 'nullable|regex:/^[0-9]{16}$/',
            'agama_ayah' => 'nullable|string|in:Islam,Kristen,Katolik,Buddha,Hindu,Konghucu',
            'agama_ibu' => 'nullable|string|in:Islam,Kristen,Katolik,Buddha,Hindu,Konghucu',
            'tempat_lahir_ayah' => 'nullable|string|max:255',
            'tempat_lahir_ibu' => 'nullable|string|max:255',
            'tanggal_lahir_ayah' => 'nullable|date',
            'tanggal_lahir_ibu' => 'nullable|date',
            'alamat_ayah' => 'nullable|string',
            'alamat_ibu' => 'nullable|string',
            'penghasilan_ayah' => 'nullable|string',
            'penghasilan_ibu' => 'nullable|string',
            'tanggal_kematian_ayah' => 'nullable|date',
            'tanggal_kematian_ibu' => 'nullable|date',
            'penyebab_kematian_ayah' => 'nullable|string|max:255',
            'penyebab_kematian_ibu' => 'nullable|string|max:255',

            // Guardian data
            'nik_wali' => 'nullable|regex:/^[0-9]{16}$/',
            'nama_wali' => 'nullable|string|max:255',
            'agama_wali' => 'nullable|string|in:Islam,Kristen,Katolik,Buddha,Hindu,Konghucu',
            'tempat_lahir_wali' => 'nullable|string|max:255',
            'tanggal_lahir_wali' => 'nullable|date',
            'alamat_wali' => 'nullable|string',
            'penghasilan_wali' => 'nullable|string',
            'hub_kerabat_wali' => 'nullable|string',

            // Education data
            'jenjang' => 'nullable|string|in:belum_sd,sd,smp,sma,perguruan_tinggi',
            'kelas' => 'nullable|string|max:255',
            'nama_sekolah' => 'nullable|string|max:255',
            'alamat_sekolah' => 'nullable|string|max:255',
            'jurusan' => 'nullable|string|max:255',
            'semester' => 'nullable|integer',
            'nama_pt' => 'nullable|string|max:255',
            'alamat_pt' => 'nullable|string|max:255',

            // Survey data
            'pekerjaan_kepala_keluarga' => 'nullable|string|max:255',
            'penghasilan' => 'nullable|string|max:255',
            'pendidikan_kepala_keluarga' => 'nullable|string|max:255',
            'jumlah_tanggungan' => 'nullable|integer',
            'kepemilikan_tabungan' => 'nullable|string|in:Ada,Tidak Ada',
            'jumlah_makan' => 'nullable|integer',
            'kepemilikan_tanah' => 'nullable|string|in:Milik Sendiri,Kontrak,Menumpang,Lainnya',
            'kepemilikan_rumah' => 'nullable|string|in:Milik Sendiri,Kontrak,Menumpang,Lainnya',
            'kondisi_rumah_dinding' => 'nullable|string|max:255',
            'kondisi_rumah_lantai' => 'nullable|string|max:255',
            'kepemilikan_kendaraan' => 'nullable|string|max:255',
            'kepemilikan_elektronik' => 'nullable|string|max:255',
            'sumber_air_bersih' => 'nullable|string|max:255',
            'jamban_limbah' => 'nullable|string|max:255',
            'tempat_sampah' => 'nullable|string|max:255',
            'perokok' => 'nullable|string|in:Ya,Tidak',
            'konsumen_miras' => 'nullable|string|in:Ya,Tidak',
            'persediaan_p3k' => 'nullable|string|in:Ada,Tidak Ada',
            'makan_buah_sayur' => 'nullable|string|in:Selalu,Kadang-kadang,Tidak Pernah',
            'solat_lima_waktu' => 'nullable|string|in:Selalu,Kadang-kadang,Tidak Pernah',
            'membaca_alquran' => 'nullable|string|in:Selalu,Kadang-kadang,Tidak Pernah',
            'majelis_taklim' => 'nullable|string|in:Aktif,Tidak Aktif',
            'membaca_koran' => 'nullable|string|in:Selalu,Kadang-kadang,Tidak Pernah',
            'pengurus_organisasi' => 'nullable|string|in:Ya,Tidak',
            'pengurus_organisasi_sebagai' => 'nullable|string|max:255',
            'kondisi_fisik_anak' => 'nullable|string|in:Normal,Disabilitas',
            'keterangan_disabilitas' => 'nullable|string',
            'kepribadian_anak' => 'nullable|string|max:255',
            'biaya_pendidikan_perbulan' => 'nullable|numeric',
            'bantuan_lembaga_formal_lain' => 'nullable|string|in:Ya,Tidak',
            'bantuan_lembaga_formal_lain_sebesar' => 'nullable|numeric',
            'kondisi_penerima_manfaat' => 'nullable|string|max:255',
        ];

        // Add conditional education rules
        if (isset($data['jenjang'])) {
            $rules = array_merge($rules, $this->getEducationRules($data['jenjang']));
        }

        // Add conditional survey rules
        $rules = array_merge($rules, $this->getConditionalSurveyRules($data));

        return $rules;
    }

    public function messages(): array
    {
        return [
            'no_kk.required' => 'Nomor KK harus diisi',
            'no_kk.regex' => 'Nomor KK harus terdiri dari 16 digit angka',
            'kepala_keluarga.required' => 'Nama kepala keluarga harus diisi',
            'status_ortu.required' => 'Status orang tua harus dipilih',
            'status_ortu.in' => 'Status orang tua tidak valid',
            'id_bank.exists' => 'Bank yang dipilih tidak valid',
            
            'nik_anak.regex' => 'NIK anak harus terdiri dari 16 digit angka',
            'nik_ayah.regex' => 'NIK ayah harus terdiri dari 16 digit angka',
            'nik_ibu.regex' => 'NIK ibu harus terdiri dari 16 digit angka',
            'nik_wali.regex' => 'NIK wali harus terdiri dari 16 digit angka',
            
            'jenjang.in' => 'Jenjang pendidikan tidak valid',
            'semester.integer' => 'Semester harus berupa angka',
            
            'foto.image' => 'File foto harus berupa gambar',
            'foto.mimes' => 'Format foto harus jpg, jpeg, atau png',
            'foto.max' => 'Ukuran foto maksimal 2MB',
        ];
    }

    public function prepareForValidation(): void
    {
        if ($this->has('bank_choice') && $this->bank_choice == 'no') {
            $this->merge([
                'id_bank' => null,
                'no_rek' => null,
                'an_rek' => null
            ]);
        }
        
        if ($this->has('telp_choice') && $this->telp_choice == 'no') {
            $this->merge([
                'no_tlp' => null,
                'an_tlp' => null
            ]);
        }

        $this->merge([
            'id_prov_ayah' => $this->id_prov_ayah ?? '1',
            'id_kab_ayah' => $this->id_kab_ayah ?? '1',
            'id_kec_ayah' => $this->id_kec_ayah ?? '1',
            'id_kel_ayah' => $this->id_kel_ayah ?? '1',
            'id_prov_ibu' => $this->id_prov_ibu ?? '1',
            'id_kab_ibu' => $this->id_kab_ibu ?? '1',
            'id_kec_ibu' => $this->id_kec_ibu ?? '1',
            'id_kel_ibu' => $this->id_kel_ibu ?? '1',
        ]);
    }

    private function getEducationRules(string $jenjang): array
    {
        $rules = [];

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

        return $rules;
    }

    private function getConditionalSurveyRules(array $data): array
    {
        $rules = [];
        
        if (isset($data['kondisi_fisik_anak']) && $data['kondisi_fisik_anak'] === 'Disabilitas') {
            $rules['keterangan_disabilitas'] = 'required|string';
        }
        
        if (isset($data['bantuan_lembaga_formal_lain']) && $data['bantuan_lembaga_formal_lain'] === 'Ya') {
            $rules['bantuan_lembaga_formal_lain_sebesar'] = 'required|numeric';
        }
        
        if (isset($data['pengurus_organisasi']) && $data['pengurus_organisasi'] === 'Ya') {
            $rules['pengurus_organisasi_sebagai'] = 'required|string|max:255';
        }
        
        return $rules;
    }
}