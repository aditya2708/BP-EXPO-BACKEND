<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class DonaturRaportResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id_raport' => $this->id_raport,
            'id_anak' => $this->id_anak,
            'id_semester' => $this->id_semester,
            'total_kehadiran' => $this->total_kehadiran,
            'persentase_kehadiran' => $this->persentase_kehadiran,
            'ranking' => $this->ranking,
            'catatan_wali_kelas' => $this->catatan_wali_kelas,
            'tanggal_terbit' => $this->tanggal_terbit,
            'status' => $this->status,
            'nilai_rata_rata' => $this->raportDetail ? $this->raportDetail->avg('nilai_akhir') : null,
            'anak' => [
                'id_anak' => $this->anak?->id_anak,
                'full_name' => $this->anak?->full_name,
                'nick_name' => $this->anak?->nick_name,
                'foto_url' => $this->anak?->foto_url,
            ],
            'semester' => [
                'id_semester' => $this->semester?->id_semester,
                'nama_semester' => $this->semester?->nama_semester,
                'tahun_ajaran' => $this->semester?->tahun_ajaran,
                'is_active' => $this->semester?->is_active,
            ],
            'raport_detail' => $this->whenLoaded('raportDetail', function () {
                return $this->raportDetail->map(function ($detail) {
                    return [
                        'id_raport_detail' => $detail->id_raport_detail,
                        'mata_pelajaran' => $detail->mata_pelajaran,
                        'nilai_akhir' => $detail->nilai_akhir,
                        'nilai_huruf' => $detail->nilai_huruf,
                        'kkm' => $detail->kkm,
                        'keterangan' => $detail->keterangan,
                    ];
                });
            }),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}