<?php

namespace App\Notifications\Concerns;

use App\Services\Notifications\NotificationPreferences;

/** Notifications declare a type; channels come from the user's preferences. */
trait PreferenceAware
{
    public function via(object $notifiable): array
    {
        return app(NotificationPreferences::class)->channels($notifiable, $this->preferenceType());
    }

    abstract protected function preferenceType(): string;
}
