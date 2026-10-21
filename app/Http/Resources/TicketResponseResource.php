<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TicketResponseResource extends JsonResource
{

    public function toArray($request)
    {

        return [
            'id' => $this->id,
            'ticket' => $this->ticket_id,
            'response' => $this->response,
            'reply_at' => $this->reply_at,
            'user' => $this->user
        ];
    }
}
