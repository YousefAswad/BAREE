<?php

namespace App\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class PhoneLink extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'user_id', 'phone', 'phone_hash', 'otp_hash', 'otp_attempts', 'otp_expires_at', 'verified_at', 'opted_in_at', 'opted_out_at', 'last_inbound_at'];

    protected $hidden = ['phone', 'phone_hash', 'otp_hash'];

    protected function casts(): array
    {
        return ['phone' => 'encrypted', 'otp_expires_at' => 'datetime', 'verified_at' => 'datetime', 'opted_in_at' => 'datetime', 'opted_out_at' => 'datetime', 'last_inbound_at' => 'datetime'];
    }

    public static function hashPhone(string $e164): string
    {
        return hash_hmac('sha256', $e164, (string) config('app.key'));
    }

    public function canReceive(): bool
    {
        return $this->verified_at !== null && $this->opted_in_at !== null && $this->opted_out_at === null;
    }
}
