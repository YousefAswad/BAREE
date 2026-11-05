<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Not tenant-scoped: created and read by BAREE staff in platform mode. */
class SupportSession extends Model
{
    protected $fillable = ['tenant_id', 'staff_id', 'reason', 'started_at', 'expires_at', 'ended_at'];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'expires_at' => 'datetime', 'ended_at' => 'datetime'];
    }

    public function isActive(): bool
    {
        return $this->ended_at === null && $this->expires_at->isFuture();
    }
}
