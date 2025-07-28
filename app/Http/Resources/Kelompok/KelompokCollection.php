<?php
namespace App\Http\Resources\Kelompok;

use Illuminate\Http\Resources\Json\JsonResource;

class KelompokCollection extends JsonResource
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
        'id_kelompok' => $this->id_kelompok,
        'nama_kelompok' => $this->nama_kelompok,
        'jumlah_anggota' => $this->jumlah_anggota ?? 0,
        
        'shelter' => $this->whenLoaded('shelter', function () {
            return [
                'id_shelter' => $this->shelter->id_shelter,
                'nama_shelter' => $this->shelter->nama_shelter ?? 'Tidak Ada Shelter',
            ];
        }, function() {
            return ['nama_shelter' => 'Tidak Ada Shelter'];
        }),
        
        'level_anak_binaan' => $this->whenLoaded('levelAnakBinaan', function () {
            return [
                'id_level_anak_binaan' => $this->levelAnakBinaan->id_level_anak_binaan,
                'nama_level_binaan' => $this->levelAnakBinaan->nama_level_binaan ?? 'Tidak Ada Level', 
            ];
        }, function() {
            return ['nama_level_binaan' => 'Tidak Ada Level'];
        }),
        
        'anak_count' => $this->anak()->count(),
        'anak' => $this->whenLoaded('anak', function () {
            return $this->anak->map(function($anak) {
                return [
                    'id_anak' => $anak->id_anak,
                    'full_name' => $anak->full_name,
                    'foto_url' => $anak->foto_url,
                    'jenis_kelamin' => $anak->jenis_kelamin,
                    'agama' => $anak->agama
                ];
            });
        }, function() {
            return [];
        }),
    ];
}
}