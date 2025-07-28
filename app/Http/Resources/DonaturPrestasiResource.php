<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class DonaturPrestasiResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id_prestasi' => $this->id_prestasi,
            'id_anak' => $this->id_anak,
            'jenis_prestasi' => $this->jenis_prestasi,
            'level_prestasi' => $this->level_prestasi,
            'nama_prestasi' => $this->nama_prestasi,
            'foto' => $this->foto,
            'foto_url' => $this->foto_url,
            'tgl_upload' => $this->tgl_upload,
            'is_read' => $this->is_read,
            'anak' => [
                'id_anak' => $this->anak?->id_anak,
                'full_name' => $this->anak?->full_name,
                'nick_name' => $this->anak?->nick_name,
                'foto_url' => $this->anak?->foto_url,
            ],
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}