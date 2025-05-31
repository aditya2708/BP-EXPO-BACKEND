<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;

class DonaturAnakResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id_anak' => $this->id_anak,
            'full_name' => $this->full_name,
            'nick_name' => $this->nick_name,
            'jenis_kelamin' => $this->jenis_kelamin,
            'tempat_lahir' => $this->tempat_lahir,
            'tanggal_lahir' => $this->tanggal_lahir,
            'umur' => $this->tanggal_lahir ? Carbon::parse($this->tanggal_lahir)->age : null,
            'agama' => $this->agama,
            'anak_ke' => $this->anak_ke,
            'dari_bersaudara' => $this->dari_bersaudara,
            'tinggal_bersama' => $this->tinggal_bersama,
            'jenis_anak_binaan' => $this->jenis_anak_binaan,
            'status_cpb' => $this->status_cpb,
            'hafalan' => $this->hafalan,
            'pelajaran_favorit' => $this->pelajaran_favorit,
            'hobi' => $this->hobi,
            'prestasi' => $this->prestasi,
            'jarak_rumah' => $this->jarak_rumah,
            'transportasi' => $this->transportasi,
            'foto' => $this->foto,
            'foto_url' => $this->foto_url,
            'status_validasi' => $this->status_validasi,
            'shelter' => [
                'id_shelter' => $this->shelter?->id_shelter,
                'nama_shelter' => $this->shelter?->nama_shelter,
                'alamat' => $this->shelter?->alamat,
            ],
            'kelompok' => [
                'id_kelompok' => $this->kelompok?->id_kelompok,
                'nama_kelompok' => $this->kelompok?->nama_kelompok,
                'level' => $this->kelompok?->level,
            ],
            'keluarga' => [
                'id_keluarga' => $this->keluarga?->id_keluarga,
                'no_kk' => $this->keluarga?->no_kk,
                'nama_ayah' => $this->keluarga?->nama_ayah,
                'nama_ibu' => $this->keluarga?->nama_ibu,
            ],
            'level_anak_binaan' => [
                'id_level_anak_binaan' => $this->levelAnakBinaan?->id_level_anak_binaan,
                'nama_level' => $this->levelAnakBinaan?->nama_level,
            ],
            'pendidikan' => [
                'id_anak_pend' => $this->anakPendidikan?->id_anak_pend,
                'tingkat_pendidikan' => $this->anakPendidikan?->tingkat_pendidikan,
                'nama_sekolah' => $this->anakPendidikan?->nama_sekolah,
                'kelas' => $this->anakPendidikan?->kelas,
            ],
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}