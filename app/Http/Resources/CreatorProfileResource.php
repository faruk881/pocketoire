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
            'store_id' => $this->storefront->id,
            'store_name' => $this->storefront->name,
            'store_bio' => $this->storefront->bio,
            'user_id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'storefront_url' => $this->storefront->slug,
            'tiktok_link' => $this->storefront->tiktok_link,
            'instagram_link' => $this->storefront->instagram_link
        ];
    }
}
