<?php

namespace App\Http\Resources\Raport;

use Illuminate\Http\Resources\Json\JsonResource;

class RaportCollection extends JsonResource
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
            'id_raport' => $this->id_raport,
            'id_anak' => $this->id_anak,
            'tingkat' => $this->tingkat,
            'kelas' => $this->kelas,
            'nilai_max' => $this->nilai_max,
            'nilai_min' => $this->nilai_min,
            'nilai_rata_rata' => $this->nilai_rata_rata,
            'semester' => $this->semester,
            'is_read' => $this->is_read,
            'tanggal' => $this->tanggal,
            
            // Include related data
            'anak' => $this->whenLoaded('anak', function () {
                return [
                    'id_anak' => $this->anak->id_anak,
                    'nama_lengkap' => $this->anak->full_name ?? null,
                ];
            }),
            
            // Optional: Add foto rapor if needed
            'foto_rapor' => $this->whenLoaded('fotoRapor', function () {
                return $this->fotoRapor->map(function($foto) {
                    return [
                        'id_foto' => $foto->id_foto,
                        'nama' => $foto->nama,
                        'foto_url' => url("storage/Raport/{$this->id_raport}/{$foto->nama}")
                    ];
                });
            }),
        ];
    }
}