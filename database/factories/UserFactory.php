<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** Replaces the starter kit factory: users always belong to a tenant unless made platform admins. */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= 'password',
            'remember_token' => Str::random(10),
            'status' => 'active',
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (User $user) {
            if ($user->tenant_id === null && ! $user->is_platform_admin) {
                $user->tenant_id = Tenant::factory()->create()->id;
            }
        });
    }

    public function unverified(): static
    {
        return $this->state(fn () => ['email_verified_at' => null]);
    }

    public function withTwoFactor(): static
    {
        return $this->state(fn () => [
            'two_factor_secret' => encrypt('secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code-1'])),
            'two_factor_confirmed_at' => now(),
        ]);
    }

    public function forTenant(Tenant $tenant): static
    {
        return $this->afterMaking(fn (User $user) => $user->tenant_id = $tenant->id);
    }

    public function platformAdmin(): static
    {
        return $this->afterMaking(function (User $user) {
            $user->is_platform_admin = true;
            $user->tenant_id = null;
        });
    }
}
