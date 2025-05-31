<?php

namespace App\Http\Resources\Histori;

use Illuminate\Http\Resources\Json\JsonResource;

class HistoriCollection extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id_histori' => $this->id_histori,
            'id_anak' => $this->id_anak,
            'jenis_histori' => $this->jenis_histori,
            'nama_histori' => $this->nama_histori,
            'di_opname' => $this->di_opname,
            'dirawat_id' => $this->dirawat_id,
            'foto_url' => $this->foto ? url("storage/Histori/{$this->id_anak}/{$this->foto}") : null,
            'tanggal' => $this->tanggal,
            'is_read' => $this->is_read,
            'anak' => $this->whenLoaded('anak', function () {
                return [
                    'id_anak' => $this->anak->id_anak,
                    'full_name' => $this->anak->full_name,
                ];
            }),
            'dirawat_entity' => $this->whenLoaded('dirawatEntity', function () {
                return [
                    'id' => $this->dirawatEntity->id_anak,
                    'name' => $this->dirawatEntity->full_name,
                ];
            }),
        ];
    }
}