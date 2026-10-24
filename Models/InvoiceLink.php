<?php

namespace SombrillaCloud\Models;

use ErpFacturacion\Models\Invoice;
use Studio\Models\Model;

/** Factura del ERP emitida por Sombrilla Cloud (pedido nuevo o renovación). */
class InvoiceLink extends Model
{
    protected $table = 'scloud_invoices';

    protected $casts = [
        'period_from'  => 'date',
        'period_to'    => 'date',
        'processed_at' => 'datetime',
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function service()
    {
        return $this->belongsTo(Service::class, 'service_id');
    }
}
