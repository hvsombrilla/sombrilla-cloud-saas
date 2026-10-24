<?php

namespace SombrillaCloud\Controllers\Admin;

use Illuminate\Http\Request;
use SombrillaCloud\Models\Ticket;
use SombrillaCloud\Models\TicketReply;
use SombrillaCloud\Support\Files;
use SombrillaCloud\Support\Mailer;

class TicketController
{
    public function index(Request $request)
    {
        $status = (string) $request->query('estado', 'pending');
        $q      = trim((string) $request->query('q'));
        $tickets = Ticket::with('customer')
            ->when($status === 'pending', fn($x) => $x->whereIn('status', ['open', 'customer_reply']))
            ->when(isset(Ticket::STATUSES[$status]), fn($x) => $x->where('status', $status))
            ->when($q !== '', fn($x) => $x->where(fn($w) => $w->where('code', 'like', "%{$q}%")->orWhere('subject', 'like', "%{$q}%")))
            ->orderByDesc('last_reply_at')->paginate(25)->appends($request->query());

        return scloud_view('admin.tickets', ['tickets' => $tickets, 'status' => $status, 'q' => $q]);
    }

    public function show(int $id)
    {
        $ticket = Ticket::with('customer', 'service.plan')->findOrFail($id);

        return scloud_view('admin.ticket', ['ticket' => $ticket, 'replies' => $ticket->replies()->get()]);
    }

    public function reply(Request $request, int $id)
    {
        $ticket   = Ticket::with('customer')->findOrFail($id);
        $message  = trim((string) $request->input('message'));
        $internal = (bool) $request->input('internal');
        if ($message === '') {
            flash('error', 'Escribe la respuesta.');

            return redirect(url('/scloud/tickets/' . $id));
        }
        $file = null;
        if ($request->file('attachment')) {
            $file = Files::store($request->file('attachment'), 'tickets');
            if (!$file['ok']) {
                flash('error', $file['error']);

                return redirect(url('/scloud/tickets/' . $id));
            }
        }
        $user  = ss_current_user();
        $reply = TicketReply::create([
            'ticket_id' => $ticket->id, 'author_type' => 'staff', 'author_id' => $user?->id, 'author_name' => $user?->name ?: 'Soporte',
            'message' => mb_substr($message, 0, 20000), 'is_internal' => $internal,
            'attachment' => $file['path'] ?? null, 'attachment_name' => $file['name'] ?? null,
        ]);
        if (!$internal) {
            $new = in_array($request->input('status'), ['answered', 'on_hold', 'closed'], true) ? $request->input('status') : 'answered';
            $ticket->update(['status' => $new, 'last_reply_at' => now()]);
            Mailer::ticketReplyToCustomer($ticket->fresh('customer'), $reply);
        }
        flash('success', $internal ? 'Nota interna guardada.' : 'Respuesta enviada al cliente.');

        return redirect(url('/scloud/tickets/' . $id));
    }

    public function status(Request $request, int $id)
    {
        $ticket = Ticket::findOrFail($id);
        $data   = [];
        if (array_key_exists($request->input('status'), Ticket::STATUSES)) {
            $data['status'] = $request->input('status');
        }
        if (array_key_exists($request->input('priority'), Ticket::PRIORITIES)) {
            $data['priority'] = $request->input('priority');
        }
        if ($data) {
            $ticket->update($data);
        }
        flash('success', 'Ticket actualizado.');

        return redirect(url('/scloud/tickets/' . $id));
    }

    public function attachment(int $id)
    {
        $reply = TicketReply::findOrFail($id);

        return Files::response($reply->attachment, $reply->attachment_name);
    }
}
