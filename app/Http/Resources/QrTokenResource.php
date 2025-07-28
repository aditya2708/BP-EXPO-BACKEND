<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class QrTokenResource extends JsonResource
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
            'id_qr_token' => $this->id_qr_token,
            'id_anak' => $this->id_anak,
            'anak' => [
                'id_anak' => $this->whenLoaded('anak', function () {
                    return $this->anak->id_anak;
                }),
                'name' => $this->whenLoaded('anak', function () {
                    return $this->anak->full_name;
                }),
                'nickname' => $this->whenLoaded('anak', function () {
                    return $this->anak->nick_name;
                }),
                'foto_url' => $this->whenLoaded('anak', function () {
                    return $this->anak->foto_url;
                }),
            ],
            'token' => $this->token,
            'valid_until' => $this->valid_until ? $this->valid_until->format('Y-m-d H:i:s') : null,
            'is_active' => $this->is_active,
            'is_valid' => $this->isValid(),
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
        ];
    }
}