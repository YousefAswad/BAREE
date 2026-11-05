<?php

namespace App\Notifications;

use App\Models\Invoice;
use App\Notifications\Concerns\PreferenceAware;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class InvoiceIssuedNotification extends Notification implements ShouldQueue
{
    use PreferenceAware, Queueable;

    public function __construct(private Invoice $invoice) {}

    protected function preferenceType(): string
    {
        return 'billing';
    }

    public function toArray(object $notifiable): array
    {
        return ['type' => 'billing', 'invoice' => $this->invoice->public_id, 'number' => $this->invoice->number,
            'total' => $this->invoice->total_cents, 'currency' => $this->invoice->currency, 'due_at' => $this->invoice->due_at];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject(__('فاتورة جديدة :number', ['number' => $this->invoice->number]))
            ->line(__('المبلغ: :amount :currency', ['amount' => number_format($this->invoice->total_cents / 100, 2), 'currency' => $this->invoice->currency]))
            ->action(__('عرض الفاتورة والدفع'), url('/admin/billing'));
    }
}
