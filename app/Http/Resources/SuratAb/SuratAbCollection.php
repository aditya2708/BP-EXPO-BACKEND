<?php

namespace App\Http\Resources\SuratAb;

use Illuminate\Http\Resources\Json\JsonResource;

class SuratAbCollection extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id_surat' => $this->id_surat,
            'id_anak' => $this->id_anak,
            'pesan' => $this->pesan,
            'foto_url' => $this->foto ? url("storage/SuratAb/{$this->id_anak}/{$this->foto}") : null,
            'tanggal' => $this->tanggal,
            'is_read' => $this->is_read,
            'anak' => $this->whenLoaded('anak', function () {
                return [
                    'id_anak' => $this->anak->id_anak,
                    'full_name' => $this->anak->full_name,
                ];
            }),
        ];
    }
}