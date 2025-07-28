<?php

namespace App\Http\Requests\AdminShelter;

class KeluargaValidationRules
{
    public static function getBaseRules(): array
    {
        return [
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
        ];
    }

    public static function getParentRules(string $statusOrtu): array
    {
        $rules = [];
        
        // Father fields based on status
        if (in_array($statusOrtu, ['piatu', 'dhuafa', 'non dhuafa'])) {
            $rules = array_merge($rules, [
                'nik_ayah' => 'required|regex:/^[0-9]{16}$/',
                'agama_ayah' => 'required|string|in:Islam,Kristen,Katolik,Buddha,Hindu,Konghucu',
                'tempat_lahir_ayah' => 'required|string|max:255',
                'tanggal_lahir_ayah' => 'required|date',
                'alamat_ayah' => 'required|string',
                'penghasilan_ayah' => 'required|string',
            ]);
        }
        
        // Mother fields based on status
        if (in_array($statusOrtu, ['yatim', 'dhuafa', 'non dhuafa'])) {
            $rules = array_merge($rules, [
                'nik_ibu' => 'required|regex:/^[0-9]{16}$/',
                'agama_ibu' => 'required|string|in:Islam,Kristen,Katolik,Buddha,Hindu,Konghucu',
                'tempat_lahir_ibu' => 'required|string|max:255',
                'tanggal_lahir_ibu' => 'required|date',
                'alamat_ibu' => 'required|string',
                'penghasilan_ibu' => 'required|string',
            ]);
        }
        
        // Guardian fields for yatim piatu
        if ($statusOrtu === 'yatim piatu') {
            $rules = array_merge($rules, [
                'nik_wali' => 'required|regex:/^[0-9]{16}$/',
                'nama_wali' => 'required|string|max:255',
                'agama_wali' => 'required|string|in:Islam,Kristen,Katolik,Buddha,Hindu,Konghucu',
                'tempat_lahir_wali' => 'required|string|max:255',
                'tanggal_lahir_wali' => 'required|date',
                'alamat_wali' => 'required|string',
                'penghasilan_wali' => 'required|string',
                'hub_kerabat_wali' => 'required|string',
            ]);
        }
        
        return $rules;
    }

    public static function getEducationRules(string $jenjang): array
    {
        $rules = [
            'jenjang' => 'required|string|in:belum_sd,sd,smp,sma,perguruan_tinggi',
            'kelas' => 'nullable|string|max:255',
            'nama_sekolah' => 'nullable|string|max:255',
            'alamat_sekolah' => 'nullable|string|max:255',
            'jurusan' => 'nullable|string|max:255',
            'semester' => 'nullable|integer',
            'nama_pt' => 'nullable|string|max:255',
            'alamat_pt' => 'nullable|string|max:255',
        ];

        // Education fields based on jenjang
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

    public static function getSurveyRules(): array
    {
        return [
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
    }

    public static function getConditionalSurveyRules(array $data): array
    {
        $rules = [];
        
        // Conditional survey fields
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

    public static function getBankPhoneRules(array $data): array
    {
        $rules = [];
        
        // Bank fields if bank is chosen
        if (isset($data['bank_choice']) && $data['bank_choice'] === 'yes') {
            $rules['id_bank'] = 'required|exists:bank,id_bank';
            $rules['no_rek'] = 'required|string|max:255';
            $rules['an_rek'] = 'required|string|max:255';
        }
        
        // Phone fields if phone is chosen
        if (isset($data['telp_choice']) && $data['telp_choice'] === 'yes') {
            $rules['no_tlp'] = 'required|string|max:255';
            $rules['an_tlp'] = 'required|string|max:255';
        }
        
        return $rules;
    }

    public static function getUpdateRules(): array
    {
        return [
            'no_kk' => 'sometimes|required|regex:/^[0-9]{16}$/',
            'kepala_keluarga' => 'sometimes|required|string|max:255',
            'status_ortu' => 'sometimes|required|string|in:yatim,piatu,yatim piatu,dhuafa,non dhuafa',
            'id_bank' => 'nullable|exists:bank,id_bank',
            'no_rek' => 'nullable|string|max:255',
            'an_rek' => 'nullable|string|max:255',
            'no_tlp' => 'nullable|string|max:255',
            'an_tlp' => 'nullable|string|max:255',
        ];
    }
}