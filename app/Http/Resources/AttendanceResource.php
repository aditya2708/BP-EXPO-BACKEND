<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AttendanceResource extends JsonResource
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
            'id_absen' => $this->id_absen,
            'absen' => $this->absen,
            'id_absen_user' => $this->id_absen_user,
            'is_verified' => $this->is_verified,
            'verification_status' => $this->verification_status,
            'absen_user' => $this->whenLoaded('absenUser', function () {
                $result = [
                    'id_absen_user' => $this->absenUser->id_absen_user ?? null,
                    'id_anak' => $this->absenUser->id_anak ?? null,
                    'id_tutor' => $this->absenUser->id_tutor ?? null
                ];

                // Check if anak relation exists on absenUser
                if ($this->absenUser->relationLoaded('anak') && $this->absenUser->anak) {
                    $result['anak'] = [
                        'id_anak' => $this->absenUser->anak->id_anak,
                        'name' => $this->absenUser->anak->full_name,
                        'foto_url' => $this->absenUser->anak->foto_url
                    ];
                }

                // Similar check for tutor relation
                if ($this->absenUser->relationLoaded('tutor') && $this->absenUser->tutor) {
                    $result['tutor'] = [
                        'id_tutor' => $this->absenUser->tutor->id_tutor,
                        'nama' => $this->absenUser->tutor->nama
                    ];
                }

                return $result;
            }),
            'aktivitas' => $this->whenLoaded('aktivitas', function () {
                return [
                    'id_aktivitas' => $this->aktivitas->id_aktivitas,
                    'jenis_kegiatan' => $this->aktivitas->jenis_kegiatan,
                    'materi' => $this->aktivitas->materi,
                    'tanggal' => $this->aktivitas->tanggal,
                ];
            }),
            'verifications' => $this->whenLoaded('verifications', function () {
                return $this->verifications->map(function ($verification) {
                    return [
                        'id_verification' => $verification->id_verification,
                        'verification_method' => $verification->verification_method,
                        'is_verified' => $verification->is_verified,
                        'verification_notes' => $verification->verification_notes,
                        'verified_by' => $verification->verified_by,
                        'verified_at' => $verification->verified_at ? $verification->verified_at->format('Y-m-d H:i:s') : null,
                    ];
                });
            }),
            'latest_verification' => $this->whenLoaded('verifications', function () {
                $latest = $this->verifications->sortByDesc('verified_at')->first();
                if ($latest) {
                    return [
                        'id_verification' => $latest->id_verification,
                        'verification_method' => $latest->verification_method,
                        'is_verified' => $latest->is_verified,
                        'verification_notes' => $latest->verification_notes,
                        'verified_by' => $latest->verified_by,
                        'verified_at' => $latest->verified_at ? $latest->verified_at->format('Y-m-d H:i:s') : null,
                    ];
                }
                return null;
            }),
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
        ];
    }
}