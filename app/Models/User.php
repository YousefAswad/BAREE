<?php

namespace App\Models;

use App\Authorization\PermissionResolver;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements HasLocalePreference, MustVerifyEmail, PasskeyUser
{
    use HasApiTokens, HasFactory, HasUlids, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    // tenant_id and is_platform_admin are deliberately NOT fillable.
    protected $fillable = ['name', 'email', 'password', 'locale', 'profile', 'department_id', 'campus_id', 'job_title_id', 'manager_id', 'status'];

    protected $hidden = ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_active_at' => 'datetime',
            'password' => 'hashed',
            'profile' => 'array',
            'is_platform_admin' => 'boolean',
        ];
    }

    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function jobTitle(): BelongsTo
    {
        return $this->belongsTo(JobTitle::class);
    }

    public function segments(): BelongsToMany
    {
        return $this->belongsToMany(Segment::class);
    }

    public function passport(): HasOne
    {
        return $this->hasOne(Passport::class);
    }

    /** Queued notifications and mails are rendered in the recipient's language. */
    public function preferredLocale(): string
    {
        return $this->locale === 'en' ? 'en' : 'ar';
    }

    public function roleTemplateKey(): ?string
    {
        return $this->jobTitle?->role_template_key;
    }

    public function hasPermission(string $permission, $target = null): bool
    {
        return app(PermissionResolver::class)->allows($this, $permission, $target);
    }
}
