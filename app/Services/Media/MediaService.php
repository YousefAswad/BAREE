<?php

namespace App\Services\Media;

use App\AI\Exceptions\AiPolicyViolation;
use App\AI\Privacy\PiiRedactor;
use App\Jobs\GenerateMediaJob;
use App\Models\MediaJob;
use App\Models\Plan;
use App\Models\User;
use App\Models\Workspace;
use App\Services\CreditService;
use App\Services\Institution\ProtectedTermsRepository;
use App\Services\Studio\WorkspaceService;
use App\Support\Audit;

final class MediaService
{
    public const TYPES = ['image', 'speech', 'video', 'model3d'];

    public function __construct(
        private ModerationGate $moderation,
        private CreditService $credits,
        private PiiRedactor $redactor,
        private ProtectedTermsRepository $terms,
        private WorkspaceService $workspaces,
        private MediaRegistry $registry,
    ) {}

    public function request(User $user, string $type, string $prompt, ?Workspace $workspace = null, array $options = []): MediaJob
    {
        if (! in_array($type, self::TYPES, true)) {
            throw new AiPolicyViolation('Unknown media type.');
        }
        if (! $user->hasPermission('ai.agents.use')) {
            throw new AiPolicyViolation('You do not have access to AI tools.');
        }
        if ($type !== 'image' && ! $this->planAllowsMedia($user)) {
            throw new AiPolicyViolation(__('Advanced media is not part of your institution\'s plan.'));
        }
        if ($workspace && ! in_array($this->workspaces->access($user, $workspace), ['owner', 'editor'], true)) {
            throw new AiPolicyViolation('You cannot save into this workspace.');
        }
        if ($type === 'speech' && mb_strlen($prompt) > (int) config('media.speech.max_chars')) {
            throw new AiPolicyViolation(__('Text is too long for one voice-over.'));
        }

        // Names never reach a media provider either. Speech keeps placeholders out by refusing them.
        $redacted = $this->redactor->redact($prompt, array_merge(
            (array) ($user->tenant?->setting('privacy.protected_names') ?? []), $this->terms->forTenant($user->tenant_id)));
        if ($redacted['count'] > 0 && $type === 'speech') {
            throw new AiPolicyViolation(__('Remove personal names or contact details from the voice-over text.'));
        }

        try {
            $this->moderation->check($redacted['text'], $type);
        } catch (MediaBlocked $e) {
            $job = MediaJob::create(['user_id' => $user->id, 'workspace_id' => $workspace?->id, 'type' => $type, 'provider' => $this->registry->driverName($type),
                'status' => 'blocked', 'request' => ['prompt' => $redacted['text']], 'error' => 'moderation']);
            Audit::log('media.blocked', $job, ['type' => $type]);

            throw new AiPolicyViolation($e->getMessage());
        }

        $cost = (int) config("media.credits.{$type}");
        $this->credits->ensureCanSpend($user->tenant_id, $cost, null);

        $job = MediaJob::create([
            'user_id' => $user->id, 'workspace_id' => $workspace?->id, 'type' => $type, 'provider' => $this->registry->driverName($type),
            'status' => 'queued', 'request' => ['prompt' => $redacted['text'], 'options' => array_intersect_key($options, array_flip(['size', 'voice', 'duration', 'aspect_ratio', 'style']))],
            'credits' => $cost,
        ]);
        GenerateMediaJob::dispatch($job->id, $user->tenant_id);
        Audit::log('media.requested', $job, ['type' => $type]);

        return $job;
    }

    private function planAllowsMedia(User $user): bool
    {
        $plan = $user->tenant?->plan_id ? Plan::query()->find($user->tenant->plan_id) : null;

        return $plan === null || (bool) ($plan->limits['media'] ?? false); // no plan = demo/local install
    }
}
