<?php

namespace SombrillaCloud\Models;

use Studio\Models\Model;

class TicketReply extends Model
{
    protected $table = 'scloud_ticket_replies';

    protected $casts = ['is_internal' => 'boolean'];

    public function ticket()
    {
        return $this->belongsTo(Ticket::class, 'ticket_id');
    }

    public function isStaff(): bool
    {
        return $this->author_type === 'staff';
    }
}
