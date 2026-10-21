<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class SocialMediaResource extends JsonResource
{

    public function toArray($request)
    {

        return [
            'id' => $this->id,
            'icon' => asset($this->social_icon),
            'link' => $this->social_link,
        ];
    }
}
