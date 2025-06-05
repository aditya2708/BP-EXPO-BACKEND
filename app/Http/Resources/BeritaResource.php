<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class BeritaResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id_berita' => $this->id_berita,
            'judul' => $this->judul,
            'konten' => $this->konten,
            'tanggal' => $this->tanggal,
            'views_berita' => $this->views_berita ?? 0,
            'likes_berita' => $this->likes_berita ?? 0,
            'status_berita' => $this->status_berita,
            'foto_url' => $this->foto_url,
            'foto2_url' => $this->foto2_url,
            'foto3_url' => $this->foto3_url,
            'photos' => array_filter([
                $this->foto_url,
                $this->foto2_url,
                $this->foto3_url
            ]),
            'kategori' => $this->whenLoaded('kategori', function() {
                return [
                    'id' => $this->kategori->id,
                    'nama' => $this->kategori->nama,
                    'link' => $this->kategori->link ?? null
                ];
            }),
            'tags' => $this->whenLoaded('tags', function() {
                return $this->tags->map(function($tag) {
                    return [
                        'id' => $tag->id,
                        'nama' => $tag->nama,
                        'link' => $tag->link ?? null
                    ];
                });
            }),
            'komentar_count' => $this->whenLoaded('komentar', function() {
                return $this->komentar->count();
            }),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at
        ];
    }
}