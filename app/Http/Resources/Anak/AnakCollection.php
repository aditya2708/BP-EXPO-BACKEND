<?php

namespace App\Http\Resources\Anak;

use Illuminate\Http\Resources\Json\JsonResource;

class AnakCollection extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id_anak' => $this->id_anak,
            'id_keluarga' => $this->id_keluarga,
            'id_anak_pend' => $this->id_anak_pend,
            'id_kelompok' => $this->id_kelompok,
            'id_shelter' => $this->id_shelter,
            'id_donatur' => $this->id_donatur,
            'id_level_anak_binaan' => $this->id_level_anak_binaan,
            'nik_anak' => $this->nik_anak,
            'anak_ke' => $this->anak_ke,
            'dari_bersaudara' => $this->dari_bersaudara,
            'nick_name' => $this->nick_name,
            'full_name' => $this->full_name,
            'agama' => $this->agama,
            'tempat_lahir' => $this->tempat_lahir,
            'tanggal_lahir' => $this->tanggal_lahir,
            'jenis_kelamin' => $this->jenis_kelamin,
            'tinggal_bersama' => $this->tinggal_bersama,
            'status_validasi' => $this->status_validasi,
            'status_cpb' => $this->status_cpb,
            'jenis_anak_binaan' => $this->jenis_anak_binaan,
            'hafalan' => $this->hafalan,
            'pelajaran_favorit' => $this->pelajaran_favorit,
            'hobi' => $this->hobi,
            'prestasi' => $this->prestasi,
            'jarak_rumah' => $this->jarak_rumah,
            'transportasi' => $this->transportasi,
            'foto' => $this->foto,
            'foto_url' => $this->foto_url,
            'status' => $this->status,

            // Optional relations if needed
            'kelompok' => $this->whenLoaded('kelompok', function () {
                return [
                    'id_kelompok' => $this->kelompok->id_kelompok,
                    'nama_kelompok' => $this->kelompok->nama_kelompok,
                ];
            }),
            'shelter' => $this->whenLoaded('shelter', function () {
                return [
                    'id_shelter' => $this->shelter->id_shelter,
                    'nama_shelter' => $this->shelter->nama_shelter,
                ];
            }),
            'keluarga' => $this->whenLoaded('keluarga', function () {
                return [
                    'id_keluarga' => $this->keluarga->id_keluarga,
                    'kepala_keluarga' => $this->keluarga->kepala_keluarga,
                ];
            }),
        ];
    }
}