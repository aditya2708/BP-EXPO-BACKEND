<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class MateriResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id_materi' => $this->id_materi,
            'mata_pelajaran' => $this->mata_pelajaran,
            'nama_materi' => $this->nama_materi,
            'display_name' => $this->mata_pelajaran . ' - ' . $this->nama_materi,
            'level' => $this->whenLoaded('levelAnakBinaan')
        ];
    }
}