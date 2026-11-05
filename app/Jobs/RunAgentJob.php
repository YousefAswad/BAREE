<?php

namespace App\Jobs;

use App\AI\Exceptions\AiPolicyViolation;
use App\AI\Gateway\AiGateway;
use App\Events\AgentRunDelta;
use App\Events\AgentRunFinished;
use App\Models\User;
use App\Models\Workspace;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class RunAgentJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;      // failover happens inside the gateway; never double-charge credits

    public int $timeout = 300;

    public function __construct(
        public int $userId,
        public string $agentKey,
        public array $inputs,
        public ?int $workspaceId,
        public ?string $taskInstructions,
        public array $fileIds = [],
        public ?string $streamId = null,
    ) {
        $this->onQueue('ai');
    }

    public function handle(AiGateway $gateway, TenantContext $context): void
    {
        $user = User::query()->findOrFail($this->userId);

        $context->runAs($user->tenant_id, function () use ($gateway, $user) {
            $workspace = $this->workspaceId ? Workspace::query()->findOrFail($this->workspaceId) : null;

            try {
                $buffer = '';
                $last = microtime(true);
                $onDelta = $this->streamId ? function (string $piece) use ($user, &$buffer, &$last) {
                    $buffer .= $piece;
                    if (microtime(true) - $last >= 0.25) {
                        AgentRunDelta::dispatch($user->id, $this->streamId, $buffer);
                        [$buffer, $last] = ['', microtime(true)];
                    }
                } : null;

                $run = $gateway->run($user, $this->agentKey, $this->inputs, $workspace, $this->taskInstructions, $this->fileIds, $onDelta);
                if ($this->streamId && $buffer !== '') {
                    AgentRunDelta::dispatch($user->id, $this->streamId, $buffer);
                }
                AgentRunFinished::dispatch($user->id, $run->public_id, 'succeeded');
            } catch (AiPolicyViolation $e) {
                AgentRunFinished::dispatch($user->id, null, 'rejected', $e->getMessage());
            }
        });
    }
}
