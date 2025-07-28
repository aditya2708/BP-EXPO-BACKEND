<?php

namespace App\Http\Resources\Anak;

use Illuminate\Http\Resources\Json\JsonResource;

class AnakCollection extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
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
            'status_keluarga' => $this->status_keluarga,
            'keterangan_keluarga' => $this->keterangan_keluarga,
            'hafalan' => $this->hafalan,
            'pelajaran_favorit' => $this->pelajaran_favorit,
            'hobi' => $this->hobi,
            'prestasi' => $this->prestasi,
            'jarak_rumah' => $this->jarak_rumah,
            'transportasi' => $this->transportasi,
            'foto' => $this->foto,
            'foto_url' => $this->foto_url,
            'status' => $this->status,
            'background_story' => $this->background_story,
            'educational_goals' => $this->educational_goals,
            'personality_traits' => $this->personality_traits,
            'special_needs' => $this->special_needs,
            'marketplace_featured' => $this->marketplace_featured,
            'sponsorship_date' => $this->sponsorship_date,
            'umur' => $this->umur,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            
            // Related data
            'keluarga' => $this->whenLoaded('keluarga'),
            'kelompok' => $this->whenLoaded('kelompok'),
            'shelter' => $this->whenLoaded('shelter'),
            'donatur' => $this->whenLoaded('donatur'),
            'anakPendidikan' => $this->whenLoaded('anakPendidikan'),
            'levelAnakBinaan' => $this->whenLoaded('levelAnakBinaan'),
            
            // Status ortu dari keluarga
            'status_ortu' => $this->whenLoaded('keluarga', function() {
                return $this->keluarga->status_ortu ?? null;
            }),
        ];
    }
}