<?php

namespace App\Services\Studio;

use App\Models\Output;
use App\Models\ShareLink;
use App\Models\User;
use App\Support\Audit;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Str;

/** Read-only links to share a scenario or document with a class. Hashed tokens, max 30 days, revocable. */
final class ShareLinkService
{
    public const MAX_DAYS = 30;

    public function __construct(private TenantContext $context, private OutputService $outputs) {}

    /** @return array{0: ShareLink, 1: string} */
    public function create(User $user, Output $output, int $days = 7): array
    {
        $this->outputs->assertCanEdit($output, $user);
        $raw = Str::random(48);
        $link = ShareLink::create([
            'output_id' => $output->id, 'created_by' => $user->id, 'token_hash' => hash('sha256', $raw),
            'expires_at' => now()->addDays(max(1, min($days, self::MAX_DAYS))),
        ]);
        Audit::log('share.created', $output, ['days' => $days]);

        return [$link, $raw];
    }

    /** @return array{0: ShareLink, 1: Output}|null — resolved in the link's tenant, never cross-tenant */
    public function resolve(string $raw): ?array
    {
        $link = $this->context->asPlatform(fn () => ShareLink::query()->where('token_hash', hash('sha256', $raw))->first());
        if (! $link?->isActive()) {
            return null;
        }

        return $this->context->runAs($link->tenant_id, function () use ($link) {
            $link->increment('views');

            return [$link, Output::query()->findOrFail($link->output_id)];
        });
    }

    public function revoke(User $user, ShareLink $link): void
    {
        $this->outputs->assertCanEdit(Output::query()->findOrFail($link->output_id), $user);
        $link->update(['revoked_at' => now()]);
    }
}
