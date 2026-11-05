<?php

namespace App\Notifications;

use App\Models\Invoice;
use App\Notifications\Concerns\PreferenceAware;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class PaymentOverdueNotification extends Notification implements ShouldQueue
{
    use PreferenceAware, Queueable;

    public function __construct(private Invoice $invoice) {}

    protected function preferenceType(): string
    {
        return 'billing';
    }

    public function toArray(object $notifiable): array
    {
        return ['type' => 'billing', 'overdue' => true, 'invoice' => $this->invoice->public_id, 'number' => $this->invoice->number];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject(__('تذكير: فاتورة متأخرة :number', ['number' => $this->invoice->number]))
            ->line(__('لتجنّب إيقاف الخدمة، يرجى الدفع خلال :days يوم من تاريخ الاستحقاق.', ['days' => config('billing.grace_days')]))
            ->action(__('ادفع الآن'), url('/admin/billing'));
    }
}
