<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TicketResource extends JsonResource
{

    public function toArray($request)
    {

        return [
            'id' => $this->id,
            'message' => $this->message,
            'send_at' => $this->send_at,
            'status' => ($this->status == 1) ? 'open' : 'closed',
        ];
    }
}
