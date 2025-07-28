<?php

namespace App\Http\Resources\Tutor;

use Illuminate\Http\Resources\Json\JsonResource;

class TutorCollection extends JsonResource
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
            'id_tutor' => $this->id_tutor,
            'nama' => $this->nama,
            'pendidikan' => $this->pendidikan,
            'maple' => $this->maple,
            'email' => $this->email,
            'no_hp' => $this->no_hp,
            'foto_url' => $this->foto_url,
            
            // Include related data if needed
            'shelter' => $this->whenLoaded('shelter', function () {
                return [
                    'id_shelter' => $this->shelter->id_shelter,
                    'nama_shelter' => $this->shelter->nama_shelter ?? null,
                ];
            }),
            'kacab' => $this->whenLoaded('kacab', function () {
                return [
                    'id_kacab' => $this->kacab->id_kacab,
                    'nama_kacab' => $this->kacab->nama_kacab ?? null,
                ];
            }),
            'wilbin' => $this->whenLoaded('wilbin', function () {
                return [
                    'id_wilbin' => $this->wilbin->id_wilbin,
                    'nama_wilbin' => $this->wilbin->nama_wilbin ?? null,
                ];
            }),
        ];
    }
}