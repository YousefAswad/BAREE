<?php

namespace App\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use LogicException;

class CreditLedgerEntry extends Model
{
    use BelongsToTenant;

    public const UPDATED_AT = null;

    protected $table = 'credit_ledger';

    protected $fillable = ['tenant_id', 'delta', 'reason', 'reference_type', 'reference_id', 'agent_id', 'user_id', 'created_at'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('The credit ledger is append-only.'));
        static::deleting(fn () => throw new LogicException('The credit ledger is append-only.'));
    }
}
