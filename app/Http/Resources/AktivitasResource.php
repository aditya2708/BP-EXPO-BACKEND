<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AktivitasResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id_aktivitas' => $this->id_aktivitas,
            'id_shelter' => $this->id_shelter,
            'jenis_kegiatan' => $this->jenis_kegiatan,
            'level' => $this->level,
            'nama_kelompok' => $this->nama_kelompok,
            'materi' => $this->materi,
            'id_materi' => $this->id_materi,
            'materi_data' => $this->whenLoaded('materiData', function() {
                return [
                    'id_materi' => $this->materiData->id_materi,
                    'mata_pelajaran' => $this->materiData->mata_pelajaran,
                    'nama_materi' => $this->materiData->nama_materi,
                    'full_name' => $this->materiData->mata_pelajaran . ' - ' . $this->materiData->nama_materi
                ];
            }),
            'foto_1' => $this->foto_1,
            'foto_2' => $this->foto_2,
            'foto_3' => $this->foto_3,
            'foto_1_url' => $this->foto_1_url,
            'foto_2_url' => $this->foto_2_url,
            'foto_3_url' => $this->foto_3_url,
            'tanggal' => $this->tanggal,
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'late_threshold' => $this->late_threshold,
            'late_minutes_threshold' => $this->late_minutes_threshold,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'shelter' => $this->whenLoaded('shelter'),
            'absen' => $this->whenLoaded('absen')
        ];
    }
}