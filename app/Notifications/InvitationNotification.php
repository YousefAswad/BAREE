<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class InvitationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private string $institution, private string $token, private string $mailLocale = 'ar') {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__(':institution دعتك لـ BAREE', ['institution' => $this->institution], $this->mailLocale))
            ->line(__('ابدأ بتقييم قصير لمدة ١٠ دقائق، وبتاخد جوازك وأدوات جاهزة لشغلك.', [], $this->mailLocale))
            ->action(__('فعّل حسابك', [], $this->mailLocale), url('/invitations/'.$this->token))
            ->line(__('الرابط صالح لمدة ٧ أيام ولاستخدام واحد.', [], $this->mailLocale));
    }
}
