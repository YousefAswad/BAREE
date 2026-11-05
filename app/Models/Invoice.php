<?php

namespace App\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use BelongsToTenant, HasUlids;

    protected $fillable = ['tenant_id', 'subscription_id', 'number', 'currency', 'subtotal_cents', 'tax_cents', 'total_cents', 'status', 'lines', 'due_at', 'paid_at', 'provider', 'provider_ref'];

    protected function casts(): array
    {
        return ['lines' => 'array', 'due_at' => 'datetime', 'paid_at' => 'datetime'];
    }

    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
