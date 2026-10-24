<?php

namespace SombrillaCloud\Controllers\Client;

use Illuminate\Http\Request;
use SombrillaCloud\Models\Ticket;
use SombrillaCloud\Models\TicketReply;
use SombrillaCloud\Support\Files;
use SombrillaCloud\Support\Mailer;

class TicketController
{
    public function index()
    {
        return scloud_view('client.tickets', ['tickets' => scloud_customer()->tickets()->with('service')->get()]);
    }

    public function create(Request $request)
    {
        return scloud_view('client.ticket-new', [
            'services' => scloud_customer()->services()->with('plan')->whereIn('status', ['active', 'suspended', 'pending'])->get(),
            'selected' => (int) $request->query('servicio'),
        ]);
    }

    public function store(Request $request)
    {
        $customer = scloud_customer();
        $subject  = trim((string) $request->input('subject'));
        $message  = trim((string) $request->input('message'));
        $errors   = [];
        if (mb_strlen($subject) < 4) {
            $errors[] = 'Escribe un asunto.';
        }
        if (mb_strlen($message) < 10) {
            $errors[] = 'Describe tu consulta con más detalle.';
        }
        $department = array_key_exists($request->input('department'), Ticket::DEPARTMENTS) ? $request->input('department') : 'support';
        $priority   = array_key_exists($request->input('priority'), Ticket::PRIORITIES) ? $request->input('priority') : 'medium';
        $serviceId  = (int) $request->input('service_id');
        if ($serviceId && !$customer->services()->where('id', $serviceId)->exists()) {
            $serviceId = 0;
        }
        $file = null;
        if ($request->file('attachment')) {
            $file = Files::store($request->file('attachment'), 'tickets');
            if (!$file['ok']) {
                $errors[] = $file['error'];
            }
        }
        if ($errors) {
            return scloud_back_with('/cliente/tickets/nuevo', $errors, $request->all());
        }

        $ticket = Ticket::create([
            'code' => Ticket::nextCode(), 'customer_id' => $customer->id, 'service_id' => $serviceId ?: null,
            'department' => $department, 'subject' => mb_substr($subject, 0, 200), 'priority' => $priority,
            'status' => 'open', 'last_reply_at' => now(),
        ]);
        $reply = TicketReply::create([
            'ticket_id' => $ticket->id, 'author_type' => 'customer', 'author_id' => $customer->id, 'author_name' => $customer->name,
            'message' => mb_substr($message, 0, 20000),
            'attachment' => $file['path'] ?? null, 'attachment_name' => $file['name'] ?? null,
        ]);
        $this->notifyStaff($ticket->fresh('customer'), $reply, true);
        flash('success', "Ticket {$ticket->code} creado. Te responderemos lo antes posible.");

        return redirect(url('/cliente/tickets/' . $ticket->id));
    }

    public function show(int $id)
    {
        $ticket = $this->own($id);

        return scloud_view('client.ticket', [
            'ticket'  => $ticket->load('service.plan'),
            'replies' => $ticket->replies()->where('is_internal', false)->get(),
        ]);
    }

    public function reply(Request $request, int $id)
    {
        $ticket   = $this->own($id);
        $customer = scloud_customer();
        $back     = '/cliente/tickets/' . $ticket->id;
        $message  = trim((string) $request->input('message'));
        if ($message === '') {
            return scloud_back_with($back, ['Escribe tu respuesta.']);
        }
        $file = null;
        if ($request->file('attachment')) {
            $file = Files::store($request->file('attachment'), 'tickets');
            if (!$file['ok']) {
                return scloud_back_with($back, [$file['error']], $request->all());
            }
        }
        $reply = TicketReply::create([
            'ticket_id' => $ticket->id, 'author_type' => 'customer', 'author_id' => $customer->id, 'author_name' => $customer->name,
            'message' => mb_substr($message, 0, 20000),
            'attachment' => $file['path'] ?? null, 'attachment_name' => $file['name'] ?? null,
        ]);
        $ticket->update(['status' => 'customer_reply', 'last_reply_at' => now()]);
        $this->notifyStaff($ticket->fresh('customer'), $reply, false);
        flash('success', 'Respuesta enviada.');

        return redirect(url($back));
    }

    public function close(int $id)
    {
        $ticket = $this->own($id);
        $ticket->update(['status' => 'closed']);
        flash('success', 'Ticket cerrado.');

        return redirect(url('/cliente/tickets/' . $ticket->id));
    }

    public function attachment(int $id)
    {
        $reply = TicketReply::where('id', $id)->where('is_internal', false)->first();
        if (!$reply || !$reply->attachment || !Ticket::where('id', $reply->ticket_id)->where('customer_id', scloud_customer()->id)->exists()) {
            scloud_not_found();
        }

        return Files::response($reply->attachment, $reply->attachment_name);
    }

    private function own(int $id): Ticket
    {
        $ticket = Ticket::where('id', $id)->where('customer_id', scloud_customer()->id)->first();
        if (!$ticket) {
            scloud_not_found();
        }

        return $ticket;
    }

    private function notifyStaff(Ticket $ticket, TicketReply $reply, bool $new): void
    {
        Mailer::ticketToStaff($ticket, $reply, $new);
        if (function_exists('ss_notify')) {
            try {
                ss_notify('permission:scloud_manage_tickets', ($new ? 'Nuevo ticket ' : 'Respuesta en ') . $ticket->code, [
                    'type' => 'info', 'body' => $ticket->subject, 'url' => url('/scloud/tickets/' . $ticket->id),
                    'icon' => 'fa fa-life-ring', 'source' => 'sombrilla-cloud',
                ]);
            } catch (\Throwable) {
            }
        }
    }
}
