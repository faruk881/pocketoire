<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CreatorProfileResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'store_id' => $this->storefront->id,
            'store_name' => $this->storefront->name,
            'store_bio' => $this->storefront->bio,
            'name' => $this->name,
            'profile_photo' => $this->profile_photo,
            'cover_photo' => $this->cover_photo,
            'email' => $this->email,
            'storefront_url' => $this->storefront->slug,
            'tiktok_link' => $this->storefront->tiktok_link,
            'instagram_link' => $this->storefront->instagram_link
        ];
    }
}
