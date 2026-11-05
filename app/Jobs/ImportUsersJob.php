<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\UserImport;
use App\Services\Institution\InvitationService;
use App\Support\Tenancy\TenantContext;
use DomainException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class ImportUsersJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 900;

    public function __construct(public int $importId, public int $actorId) {}

    public function handle(TenantContext $context, InvitationService $invitations): void
    {
        $actor = User::query()->findOrFail($this->actorId);

        $context->runAs($actor->tenant_id, function () use ($invitations, $actor) {
            $import = UserImport::query()->findOrFail($this->importId);
            $report = $import->report ?? [];
            $invited = 0;

            foreach ($report as $i => $row) {
                if ($row['problems'] !== []) {
                    continue;
                }
                try {
                    $invitations->invite($actor, $row, $import->id);
                    $invited++;
                } catch (DomainException $e) {
                    $report[$i]['problems'][] = $e->getMessage();
                }
            }

            $import->update(['status' => 'done', 'invited' => $invited, 'report' => $report]);
        });
    }
}
