<?php

namespace SombrillaCloud\Support;

use ErpFacturacion\Models\Invoice;
use SombrillaCloud\Models\BankAccount;
use SombrillaCloud\Models\Customer;
use SombrillaCloud\Models\InvoiceLink;
use SombrillaCloud\Models\Order;
use SombrillaCloud\Models\PaymentProof;
use SombrillaCloud\Models\Service;
use SombrillaCloud\Models\Ticket;
use SombrillaCloud\Models\TicketReply;

/**
 * Correos transaccionales (HTML). Usa wp_mail() o mail(); si no hay correo configurado,
 * deja el mensaje en storage/logs/scloud-mail.log. Nunca lanza excepciones.
 */
final class Mailer
{
    public static function send(string $to, string $subject, string $view, array $data = []): bool
    {
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        try {
            $brand = (string) Settings::get('brand_name');
            $html  = view('scloud::emails.' . $view, $data + ['brand' => $brand, 'subject' => $subject])->render();
        } catch (\Throwable $e) {
            error_log('[sombrilla-cloud] correo ' . $view . ': ' . $e->getMessage());

            return false;
        }

        $host      = parse_url(url('/'), PHP_URL_HOST) ?: ($_SERVER['HTTP_HOST'] ?? 'localhost');
        $fromEmail = (string) Settings::get('mail_from_email') ?: 'no-reply@' . preg_replace('/^www\./', '', (string) $host);
        $fromName  = (string) Settings::get('mail_from_name') ?: (string) Settings::get('brand_name');
        $subject   = '=?UTF-8?B?' . base64_encode($subject) . '?=';

        $sent = false;
        try {
            if (function_exists('wp_mail')) {
                $sent = (bool) \wp_mail($to, $subject, $html, ['Content-Type: text/html; charset=UTF-8', "From: {$fromName} <{$fromEmail}>"]);
            } elseif (function_exists('mail')) {
                $headers = implode("\r\n", [
                    'MIME-Version: 1.0',
                    'Content-Type: text/html; charset=UTF-8',
                    'From: =?UTF-8?B?' . base64_encode($fromName) . "?= <{$fromEmail}>",
                    'Reply-To: ' . ((string) Settings::get('support_email') ?: $fromEmail),
                ]);
                $sent = @mail($to, $subject, $html, $headers, '-f' . $fromEmail);
            }
        } catch (\Throwable) {
            $sent = false;
        }

        if (!$sent) {
            $dir = storage_path('logs');
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            @file_put_contents($dir . '/scloud-mail.log', '[' . date('Y-m-d H:i:s') . "] {$to} · {$view}\n", FILE_APPEND);
        }

        return $sent;
    }

    public static function welcome(Customer $customer): void
    {
        self::send($customer->email, 'Bienvenido a ' . Settings::get('brand_name'), 'welcome', ['customer' => $customer]);
    }

    public static function passwordReset(Customer $customer, string $url): void
    {
        self::send($customer->email, 'Restablecer tu contraseña', 'password-reset', ['customer' => $customer, 'url' => $url]);
    }

    public static function orderPlaced(Order $order, Invoice $invoice): void
    {
        self::send($order->customer->email, "Pedido {$order->code} recibido", 'order-placed', [
            'customer' => $order->customer, 'order' => $order, 'invoice' => $invoice,
            'banks' => BankAccount::active()->get(), 'url' => url('/cliente/facturas/' . $invoice->id),
        ]);
        if ($admin = (string) Settings::get('admin_notify_email')) {
            self::send($admin, "Nuevo pedido {$order->code}", 'admin-notice', [
                'title' => "Nuevo pedido {$order->code}",
                'lines' => ['Cliente: ' . $order->customer->name . ' (' . $order->customer->email . ')', 'Total: ' . scloud_money($invoice->total)],
                'url'   => url('/scloud/pedidos/' . $order->id),
            ]);
        }
    }

    public static function invoiceCreated(Customer $customer, Invoice $invoice, ?Service $service = null): void
    {
        self::send($customer->email, "Nueva factura {$invoice->code}", 'invoice-created', [
            'customer' => $customer, 'invoice' => $invoice, 'service' => $service,
            'banks' => BankAccount::active()->get(), 'url' => url('/cliente/facturas/' . $invoice->id),
        ]);
    }

    public static function paymentConfirmed(InvoiceLink $link): void
    {
        if (!$link->customer || !$link->invoice) {
            return;
        }
        self::send($link->customer->email, "Pago recibido · factura {$link->invoice->code}", 'payment-confirmed', [
            'customer' => $link->customer, 'invoice' => $link->invoice, 'url' => url('/cliente/facturas/' . $link->invoice_id),
        ]);
    }

    public static function paymentPartial(PaymentProof $proof): void
    {
        if (!$proof->customer || !$proof->invoice) {
            return;
        }
        self::send($proof->customer->email, "Abono recibido · factura {$proof->invoice->code}", 'payment-partial', [
            'customer' => $proof->customer, 'invoice' => $proof->invoice, 'url' => url('/cliente/facturas/' . $proof->invoice_id),
        ]);
    }

    public static function proofRejected(PaymentProof $proof): void
    {
        if (!$proof->customer || !$proof->invoice) {
            return;
        }
        self::send($proof->customer->email, "Comprobante no aceptado · factura {$proof->invoice->code}", 'proof-rejected', [
            'customer' => $proof->customer, 'invoice' => $proof->invoice, 'proof' => $proof, 'url' => url('/cliente/facturas/' . $proof->invoice_id),
        ]);
    }

    public static function proofReceived(PaymentProof $proof): void
    {
        if ($admin = (string) Settings::get('admin_notify_email')) {
            self::send($admin, 'Comprobante de pago por validar', 'admin-notice', [
                'title' => 'Comprobante de pago por validar',
                'lines' => ['Cliente: ' . $proof->customer->name, 'Factura: ' . ($proof->invoice->code ?? ''), 'Monto: ' . scloud_money($proof->amount), 'Referencia: ' . $proof->reference],
                'url'   => url('/scloud/pagos'),
            ]);
        }
    }

    /** Bienvenida con los accesos de la instancia (o nuevos accesos si $reset). La contraseña solo viaja en este correo: no se guarda. */
    public static function instanceReady(Service $service, string $password, bool $reset = false): bool
    {
        $service->loadMissing('customer', 'plan');
        $subject = $reset ? 'Nuevos accesos a tu instancia: ' . $service->workspace_name : 'Tu instancia está lista: ' . $service->workspace_name;

        return self::send($service->customer->email, $subject, 'account-created', [
            'customer' => $service->customer, 'service' => $service, 'password' => $password, 'reset' => $reset,
            'dns' => $service->domain_mode === 'own' && $service->subdomain ? \SombrillaCloud\Services\DomainService::dnsInstructions($service->subdomain, $service->domain) : null,
            'url' => url('/cliente/servicios/' . $service->id),
        ]);
    }

    public static function serviceSuspended(Service $service): void
    {
        self::send($service->customer->email, 'Servicio suspendido: ' . ($service->host() ?: $service->title()), 'service-suspended', [
            'customer' => $service->customer, 'service' => $service, 'url' => url('/cliente/facturas'),
        ]);
    }

    public static function ticketReplyToCustomer(Ticket $ticket, TicketReply $reply): void
    {
        self::send($ticket->customer->email, "[{$ticket->code}] {$ticket->subject}", 'ticket-reply', [
            'customer' => $ticket->customer, 'ticket' => $ticket, 'reply' => $reply, 'url' => url('/cliente/tickets/' . $ticket->id),
        ]);
    }

    public static function ticketToStaff(Ticket $ticket, TicketReply $reply, bool $new): void
    {
        if ($admin = (string) Settings::get('admin_notify_email')) {
            self::send($admin, ($new ? 'Nuevo ticket ' : 'Respuesta en ') . "[{$ticket->code}] {$ticket->subject}", 'admin-notice', [
                'title' => ($new ? 'Nuevo ticket: ' : 'Nueva respuesta: ') . $ticket->subject,
                'lines' => ['Cliente: ' . $ticket->customer->name, 'Departamento: ' . $ticket->departmentLabel(), '', mb_substr($reply->message, 0, 800)],
                'url'   => url('/scloud/tickets/' . $ticket->id),
            ]);
        }
    }
}
