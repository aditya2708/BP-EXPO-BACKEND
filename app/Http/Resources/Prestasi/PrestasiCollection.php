<?php

namespace App\Http\Resources\Prestasi;

use Illuminate\Http\Resources\Json\JsonResource;

class PrestasiCollection extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id_prestasi' => $this->id_prestasi,
            'id_anak' => $this->id_anak,
            'jenis_prestasi' => $this->jenis_prestasi,
            'level_prestasi' => $this->level_prestasi,
            'nama_prestasi' => $this->nama_prestasi,
            'foto_url' => $this->foto ? url("storage/Prestasi/{$this->id_anak}/{$this->foto}") : null,
            'tgl_upload' => $this->tgl_upload,
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