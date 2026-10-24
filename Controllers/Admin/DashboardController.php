<?php

namespace SombrillaCloud\Controllers\Admin;

use SombrillaCloud\Models\Customer;
use SombrillaCloud\Models\Order;
use SombrillaCloud\Models\PaymentProof;
use SombrillaCloud\Models\Service;
use SombrillaCloud\Models\ServiceLog;
use SombrillaCloud\Models\Ticket;
use SombrillaCloud\Support\Cycles;
use Studio\Models\SsOption;

class DashboardController
{
    public function index()
    {
        $live = Service::whereIn('status', ['active', 'suspended'])->get(['amount', 'billing_cycle', 'status']);
        $mrr  = $live->where('status', 'active')->sum(fn($s) => $s->amount / Cycles::months($s->billing_cycle));

        return scloud_view('admin.dashboard', [
            'stats' => [
                'pending_orders' => Order::where('status', 'pending')->count(),
                'proofs'         => PaymentProof::where('status', 'pending')->count(),
                'active'         => $live->where('status', 'active')->count(),
                'suspended'      => $live->where('status', 'suspended')->count(),
                'failed'         => Service::where('status', 'failed')->count(),
                'tickets'        => Ticket::whereIn('status', ['open', 'customer_reply'])->count(),
                'customers'      => Customer::count(),
                'mrr'            => round($mrr, 2),
            ],
            'recentOrders' => Order::with('customer')->orderByDesc('id')->limit(6)->get(),
            'renewals'     => Service::with('plan', 'customer')->where('status', 'active')->whereNotNull('next_due_date')
                ->whereDate('next_due_date', '<=', date('Y-m-d', strtotime('+14 days')))->orderBy('next_due_date')->limit(8)->get(),
            'failedServices' => Service::with('plan', 'customer')->where('status', 'failed')->orderByDesc('id')->limit(5)->get(),
            'openTickets'  => Ticket::with('customer')->whereIn('status', ['open', 'customer_reply'])->orderBy('last_reply_at')->limit(6)->get(),
            'lastErrors'   => ServiceLog::where('ok', false)->orderByDesc('id')->limit(4)->get(),
            'cronLast'     => SsOption::get('scloud_cron_last_run'),
        ]);
    }
}
