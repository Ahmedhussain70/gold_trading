<?php

namespace Ticket\Models;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;


class TicketResponse extends Model
{
    protected $guarded = [];

    protected $table = 'ticket_responses';

    public function ticket()
    {
        return $this->belongsTo(Ticket::class, 'ticket_id');
    }
}
