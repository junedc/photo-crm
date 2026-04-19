<?php

use App\Models\Invoice;
use App\Models\InvoiceInstallment;

require __DIR__.'/vendor/autoload.php';

$app = require __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "webhook_secret_present=".(config('services.stripe.webhook_secret') ? 'yes' : 'no').PHP_EOL;

$invoices = Invoice::query()
    ->with(['booking', 'installments'])
    ->latest('id')
    ->limit(5)
    ->get();

foreach ($invoices as $invoice) {
    echo json_encode([
        'invoice_id' => $invoice->id,
        'invoice_number' => $invoice->invoice_number,
        'status' => $invoice->status,
        'amount_paid' => $invoice->amount_paid,
        'total_amount' => $invoice->total_amount,
        'booking_id' => $invoice->booking_id,
        'booking_status' => $invoice->booking?->status,
    ], JSON_UNESCAPED_SLASHES).PHP_EOL;

    foreach ($invoice->installments as $installment) {
        echo '  installment='.json_encode([
            'id' => $installment->id,
            'label' => $installment->label,
            'amount' => $installment->amount,
            'status' => $installment->status,
            'paid_at' => optional($installment->paid_at)?->toDateTimeString(),
        ], JSON_UNESCAPED_SLASHES).PHP_EOL;
    }
}
