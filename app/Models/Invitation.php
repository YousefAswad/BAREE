<?php

namespace App\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Invitation extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'email', 'name', 'department_id', 'job_title_id', 'role_key', 'token_hash', 'expires_at', 'accepted_at', 'revoked_at', 'invited_by', 'import_id'];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'accepted_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    public function isUsable(): bool
    {
        return $this->accepted_at === null && $this->revoked_at === null && $this->expires_at->isFuture();
    }
}
