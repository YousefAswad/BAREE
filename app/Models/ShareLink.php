<?php

namespace App\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class ShareLink extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'output_id', 'created_by', 'token_hash', 'expires_at', 'revoked_at', 'views'];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null && $this->expires_at->isFuture();
    }
}
