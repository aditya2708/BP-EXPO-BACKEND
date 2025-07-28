<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class DonaturSuratResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id_surat' => $this->id_surat,
            'id_anak' => $this->id_anak,
            'pesan' => $this->pesan,
            'foto' => $this->foto,
            'foto_url' => $this->foto ? url("storage/SuratAb/{$this->id_anak}/{$this->foto}") : null,
            'tanggal' => $this->tanggal,
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