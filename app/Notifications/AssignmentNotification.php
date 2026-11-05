<?php

namespace App\Notifications;

use App\Notifications\Concerns\PreferenceAware;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** kind: assigned | reminder | overdue */
final class AssignmentNotification extends Notification implements ShouldQueue
{
    use PreferenceAware, Queueable;

    public function __construct(private string $kind, private string $title, private string $url, private ?string $dueDate, private bool $mandatory) {}

    protected function preferenceType(): string
    {
        return 'assignment';
    }

    public function toArray(object $notifiable): array
    {
        return ['type' => 'assignment', 'kind' => $this->kind, 'title' => $this->title, 'url' => $this->url, 'due' => $this->dueDate, 'mandatory' => $this->mandatory];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $subject = match ($this->kind) {
            'reminder' => __('تذكير: :title', ['title' => $this->title]),
            'overdue' => __('متأخر: :title', ['title' => $this->title]),
            default => __('مهمة جديدة: :title', ['title' => $this->title]),
        };

        return (new MailMessage)->subject($subject)
            ->line($this->dueDate ? __('آخر موعد: :date', ['date' => $this->dueDate]) : __('بدون موعد محدد'))
            ->action(__('ابدأ'), url($this->url));
    }

    public function toWhatsApp(object $notifiable): array
    {
        return ['template' => $this->kind === 'assigned' ? 'assignment' : 'reminder', 'params' => [$this->title, $this->dueDate ?? '-']];
    }
}
