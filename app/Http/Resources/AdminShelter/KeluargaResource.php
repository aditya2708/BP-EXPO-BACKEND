<?php

namespace App\Http\Resources\AdminShelter;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class KeluargaResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id_keluarga' => $this->id_keluarga,
            'no_kk' => $this->no_kk,
            'kepala_keluarga' => $this->kepala_keluarga,
            'status_ortu' => $this->status_ortu,
            'id_kacab' => $this->id_kacab,
            'id_wilbin' => $this->id_wilbin,
            'id_shelter' => $this->id_shelter,
            'id_bank' => $this->id_bank,
            'no_rek' => $this->no_rek,
            'an_rek' => $this->an_rek,
            'no_tlp' => $this->no_tlp,
            'an_tlp' => $this->an_tlp,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
            
            // Relationships
            'shelter' => $this->whenLoaded('shelter', function () {
                return [
                    'id_shelter' => $this->shelter->id_shelter,
                    'nama_shelter' => $this->shelter->nama_shelter,
                ];
            }),
            
            'wilbin' => $this->whenLoaded('wilbin', function () {
                return [
                    'id_wilbin' => $this->wilbin->id_wilbin,
                    'nama_wilbin' => $this->wilbin->nama_wilbin,
                ];
            }),
            
            'kacab' => $this->whenLoaded('kacab', function () {
                return [
                    'id_kacab' => $this->kacab->id_kacab,
                    'nama_kacab' => $this->kacab->nama_kacab,
                ];
            }),
            
            'bank' => $this->whenLoaded('bank', function () {
                return [
                    'id_bank' => $this->bank->id_bank,
                    'nama_bank' => $this->bank->nama_bank,
                ];
            }),
            
            'ayah' => $this->whenLoaded('ayah', function () {
                return [
                    'id_ayah' => $this->ayah->id_ayah,
                    'nik_ayah' => $this->ayah->nik_ayah,
                    'nama_ayah' => $this->ayah->nama_ayah,
                    'agama' => $this->ayah->agama,
                    'tempat_lahir' => $this->ayah->tempat_lahir,
                    'tanggal_lahir' => $this->ayah->tanggal_lahir,
                    'alamat' => $this->ayah->alamat,
                    'id_prov' => $this->ayah->id_prov,
                    'id_kab' => $this->ayah->id_kab,
                    'id_kec' => $this->ayah->id_kec,
                    'id_kel' => $this->ayah->id_kel,
                    'penghasilan' => $this->ayah->penghasilan,
                    'tanggal_kematian' => $this->ayah->tanggal_kematian,
                    'penyebab_kematian' => $this->ayah->penyebab_kematian,
                ];
            }),
            
            'ibu' => $this->whenLoaded('ibu', function () {
                return [
                    'id_ibu' => $this->ibu->id_ibu,
                    'nik_ibu' => $this->ibu->nik_ibu,
                    'nama_ibu' => $this->ibu->nama_ibu,
                    'agama' => $this->ibu->agama,
                    'tempat_lahir' => $this->ibu->tempat_lahir,
                    'tanggal_lahir' => $this->ibu->tanggal_lahir,
                    'alamat' => $this->ibu->alamat,
                    'id_prov' => $this->ibu->id_prov,
                    'id_kab' => $this->ibu->id_kab,
                    'id_kec' => $this->ibu->id_kec,
                    'id_kel' => $this->ibu->id_kel,
                    'penghasilan' => $this->ibu->penghasilan,
                    'tanggal_kematian' => $this->ibu->tanggal_kematian,
                    'penyebab_kematian' => $this->ibu->penyebab_kematian,
                ];
            }),
            
            'wali' => $this->whenLoaded('wali', function () {
                return [
                    'id_wali' => $this->wali->id_wali,
                    'nik_wali' => $this->wali->nik_wali,
                    'nama_wali' => $this->wali->nama_wali,
                    'agama' => $this->wali->agama,
                    'tempat_lahir' => $this->wali->tempat_lahir,
                    'tanggal_lahir' => $this->wali->tanggal_lahir,
                    'alamat' => $this->wali->alamat,
                    'penghasilan' => $this->wali->penghasilan,
                    'hub_kerabat' => $this->wali->hub_kerabat,
                ];
            }),
            
            'surveys' => $this->whenLoaded('surveys', function () {
                return $this->surveys->map(function ($survey) {
                    return [
                        'id_survey' => $survey->id_survey,
                        'pekerjaan_kepala_keluarga' => $survey->pekerjaan_kepala_keluarga,
                        'penghasilan' => $survey->penghasilan,
                        'pendidikan_kepala_keluarga' => $survey->pendidikan_kepala_keluarga,
                        'jumlah_tanggungan' => $survey->jumlah_tanggungan,
                        'kepemilikan_tabungan' => $survey->kepemilikan_tabungan,
                        'jumlah_makan' => $survey->jumlah_makan,
                        'kepemilikan_tanah' => $survey->kepemilikan_tanah,
                        'kepemilikan_rumah' => $survey->kepemilikan_rumah,
                        'kondisi_rumah_dinding' => $survey->kondisi_rumah_dinding,
                        'kondisi_rumah_lantai' => $survey->kondisi_rumah_lantai,
                        'kepemilikan_kendaraan' => $survey->kepemilikan_kendaraan,
                        'kepemilikan_elektronik' => $survey->kepemilikan_elektronik,
                        'sumber_air_bersih' => $survey->sumber_air_bersih,
                        'jamban_limbah' => $survey->jamban_limbah,
                        'tempat_sampah' => $survey->tempat_sampah,
                        'perokok' => $survey->perokok,
                        'konsumen_miras' => $survey->konsumen_miras,
                        'persediaan_p3k' => $survey->persediaan_p3k,
                        'makan_buah_sayur' => $survey->makan_buah_sayur,
                        'solat_lima_waktu' => $survey->solat_lima_waktu,
                        'membaca_alquran' => $survey->membaca_alquran,
                        'majelis_taklim' => $survey->majelis_taklim,
                        'membaca_koran' => $survey->membaca_koran,
                        'pengurus_organisasi' => $survey->pengurus_organisasi,
                        'pengurus_organisasi_sebagai' => $survey->pengurus_organisasi_sebagai,
                        'kepribadian_anak' => $survey->kepribadian_anak,
                        'kondisi_fisik_anak' => $survey->kondisi_fisik_anak,
                        'keterangan_disabilitas' => $survey->keterangan_disabilitas,
                        'biaya_pendidikan_perbulan' => $survey->biaya_pendidikan_perbulan,
                        'bantuan_lembaga_formal_lain' => $survey->bantuan_lembaga_formal_lain,
                        'bantuan_lembaga_formal_lain_sebesar' => $survey->bantuan_lembaga_formal_lain_sebesar,
                        'kondisi_penerima_manfaat' => $survey->kondisi_penerima_manfaat,
                        'tanggal_survey' => $survey->tanggal_survey,
                        'petugas_survey' => $survey->petugas_survey,
                        'hasil_survey' => $survey->hasil_survey,
                        'keterangan_hasil' => $survey->keterangan_hasil,
                    ];
                });
            }),
            
            // Additional computed fields
            'has_bank_account' => !empty($this->id_bank),
            'has_phone' => !empty($this->no_tlp),
            'parent_status_description' => $this->getParentStatusDescription(),
        ];
    }
    
    /**
     * Get human-readable parent status description
     */
    private function getParentStatusDescription(): string
    {
        switch ($this->status_ortu) {
            case 'yatim':
                return 'Yatim (Ayah Meninggal)';
            case 'piatu':
                return 'Piatu (Ibu Meninggal)';
            case 'yatim piatu':
                return 'Yatim Piatu (Kedua Orang Tua Meninggal)';
            case 'dhuafa':
                return 'Dhuafa (Keluarga Kurang Mampu)';
            case 'non dhuafa':
                return 'Non Dhuafa (Keluarga Mampu)';
            default:
                return $this->status_ortu;
        }
    }
}