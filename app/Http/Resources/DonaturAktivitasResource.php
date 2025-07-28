<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class DonaturAktivitasResource extends JsonResource
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
            'attendance_status' => $this->attendance_status ?? 'Tidak Hadir',
            'attendance_verified' => $this->attendance_verified ?? false,
            'attendance_time' => $this->attendance_time,
            'attendance_notes' => $this->attendance_notes,
            'shelter' => [
                'id_shelter' => $this->shelter?->id_shelter,
                'nama_shelter' => $this->shelter?->nama_shelter,
                'alamat' => $this->shelter?->alamat,
            ],
            'materi_data' => [
                'id_materi' => $this->materiData?->id_materi,
                'mata_pelajaran' => $this->materiData?->mata_pelajaran,
                'nama_materi' => $this->materiData?->nama_materi,
                'level_anak_binaan' => [
                    'id_level_anak_binaan' => $this->materiData?->levelAnakBinaan?->id_level_anak_binaan,
                    'nama_level' => $this->materiData?->levelAnakBinaan?->nama_level,
                ],
            ],
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}