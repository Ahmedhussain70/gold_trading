<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class NewsResource extends JsonResource
{

    public function toArray($request)
    {

        return [
            'id' => $this->id,
            'name' => $this->title,
            'content' => strip_tags($this->content),
            'publish' => $this->publish,
            'image' => asset($this->image),
        ];
    }
}
