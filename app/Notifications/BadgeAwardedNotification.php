<?php

namespace App\Notifications;

use App\Notifications\Concerns\PreferenceAware;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

final class BadgeAwardedNotification extends Notification
{
    use PreferenceAware, Queueable;

    public function __construct(private string $badgeKey, private array $name) {}

    protected function preferenceType(): string
    {
        return 'achievement';
    }

    public function toArray(object $notifiable): array
    {
        return ['type' => 'achievement', 'badge' => $this->badgeKey, 'name' => $this->name, 'url' => '/lab'];
    }
}
