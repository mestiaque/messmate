<?php

namespace App\Notifications;

use App\Models\Invoice;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionPaymentReceived extends Notification
{
    public function __construct(private readonly Invoice $invoice) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $invoice = $this->invoice->loadMissing('tenant', 'plan');

        return (new MailMessage)
            ->subject("Payment received — invoice {$invoice->invoice_number}")
            ->line("We received {$invoice->currency} ".number_format($invoice->total, 2)." for {$invoice->description} ({$invoice->tenant->name}).")
            ->action('View invoice', route('billing.invoices.show', ['tenant' => $invoice->tenant, 'invoice' => $invoice]))
            ->line('Thank you for using '.config('app.name').'.');
    }
}
