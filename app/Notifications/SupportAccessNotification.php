<?php

namespace App\Notifications;

use App\Models\SupportSession;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/** Institution owners are always told when BAREE staff open a support session. */
final class SupportAccessNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private SupportSession $session) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toArray(object $notifiable): array
    {
        return ['type' => 'support_session', 'reason' => $this->session->reason, 'expires_at' => $this->session->expires_at];
    }

    public function toMail(object $notifiable): \Illuminate\Notifications\Messages\MailMessage
    {
        return (new \Illuminate\Notifications\Messages\MailMessage)
            ->subject(__('فريق دعم BAREE فتح جلسة دعم لمؤسستك'))
            ->line(__('السبب: :reason', ['reason' => $this->session->reason]))
            ->line(__('الجلسة للقراءة فقط وتنتهي تلقائيًا: :time', ['time' => $this->session->expires_at->toDayDateTimeString()]));
    }
}
