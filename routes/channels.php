<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('users.{userId}.runs', fn ($user, int $userId) => $user->id === $userId);
