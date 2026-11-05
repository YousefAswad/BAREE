<?php

namespace App\Services\Platform;

use App\Models\Agent;
use App\Models\AgentVersion;
use App\Models\AiProvider;
use App\Models\User;
use App\Support\Audit;
use App\Support\Tenancy\TenantContext;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Lifecycle: draft → testing → rolling_out → published → retired.
 * Only drafts are editable. Nothing reaches institutions without a passing test batch.
 */
final class AgentVersionService
{
    private const EDITABLE = ['system_prompt', 'prompt_block_keys', 'input_schema', 'output_schema', 'routing', 'customization_policy', 'credits_cost', 'release_notes', 'workflow'];

    public function __construct(private AgentEvaluationService $evaluation, private TenantContext $context) {}

    public function createDraft(Agent $agent, User $actor): AgentVersion
    {
        $base = $agent->publishedVersion ?? $agent->versions()->orderByDesc('version')->first();
        $next = (int) $agent->versions()->max('version') + 1;

        $draft = $agent->versions()->create(array_merge(
            $base ? $base->only(self::EDITABLE) : [
                'system_prompt' => '', 'input_schema' => ['fields' => []], 'routing' => ['providers' => ['anthropic'], 'tier' => 'standard'],
                'customization_policy' => ['tenant' => ['instructions' => true], 'user' => ['instructions' => true], 'task' => ['instructions' => true]],
                'credits_cost' => 5,
            ],
            ['version' => $next, 'status' => 'draft', 'created_by' => $actor->id, 'rollout' => null, 'published_at' => null],
        ));
        Audit::log('agent.draft_created', $draft, ['agent' => $agent->key, 'from' => $base?->version]);

        return $draft;
    }

    public function updateDraft(AgentVersion $version, array $data): AgentVersion
    {
        if ($version->status !== 'draft') {
            throw new DomainException('Only drafts can be edited. Create a new draft.');
        }

        $data = array_intersect_key($data, array_flip(self::EDITABLE));
        $this->validate($data + $version->only(self::EDITABLE));
        $version->update($data);
        Audit::log('agent.draft_updated', $version, ['fields' => array_keys($data)]);

        return $version;
    }

    public function submitForTesting(AgentVersion $version): void
    {
        $this->transition($version, ['draft'], 'testing');
    }

    /** @param  array{mode:string, tenant_ids?:int[], percent?:int}  $rollout */
    public function startRollout(AgentVersion $version, array $rollout): void
    {
        if (! $this->evaluation->latestBatchPasses($version)) {
            throw new DomainException('Run the test suite: every blocking case must pass before rollout.');
        }
        if (! in_array($rollout['mode'] ?? null, ['pilot', 'percent', 'all'], true)) {
            throw new DomainException('Unknown rollout mode.');
        }

        if ($rollout['mode'] === 'all') {
            $this->promote($version);

            return;
        }

        // Only one version may roll out at a time per agent.
        AgentVersion::query()->where('agent_id', $version->agent_id)->where('status', 'rolling_out')->update(['status' => 'testing', 'rollout' => null]);
        $this->transition($version, ['testing'], 'rolling_out', ['rollout' => $rollout]);
    }

    public function promote(AgentVersion $version): void
    {
        if (! in_array($version->status, ['testing', 'rolling_out'], true)) {
            throw new DomainException('Only tested versions can be published.');
        }
        if (! $this->evaluation->latestBatchPasses($version)) {
            throw new DomainException('Blocking tests are failing.');
        }

        // Global agents are platform records: always write them in platform mode.
        $this->context->asPlatform(fn () => DB::transaction(function () use ($version) {
            $agent = Agent::query()->lockForUpdate()->findOrFail($version->agent_id);
            AgentVersion::query()->where('agent_id', $agent->id)->where('status', 'published')->update(['status' => 'retired']);
            $version->update(['status' => 'published', 'rollout' => null, 'published_at' => now()]);
            $agent->update(['published_version_id' => $version->id, 'status' => 'published']);
        }));
        Audit::log('agent.published', $version, ['agent_id' => $version->agent_id, 'version' => $version->version]);
    }

    public function rollback(Agent $agent, AgentVersion $target): void
    {
        if ($target->agent_id !== $agent->id || $target->status !== 'retired') {
            throw new DomainException('You can only roll back to a retired version of this agent.');
        }

        $this->context->asPlatform(fn () => DB::transaction(function () use ($agent, $target) {
            AgentVersion::query()->where('agent_id', $agent->id)->whereIn('status', ['published', 'rolling_out'])->update(['status' => 'retired', 'rollout' => null]);
            $target->update(['status' => 'published', 'published_at' => now()]);
            $agent->update(['published_version_id' => $target->id]);
        }));
        Audit::log('agent.rolled_back', $target, ['agent' => $agent->key, 'to' => $target->version]);
    }

    private function transition(AgentVersion $version, array $from, string $to, array $extra = []): void
    {
        if (! in_array($version->status, $from, true)) {
            throw new DomainException("Cannot move from {$version->status} to {$to}.");
        }
        $version->update(['status' => $to] + $extra);
        Audit::log('agent.version_'.$to, $version);
    }

    private function validate(array $v): void
    {
        if (trim((string) ($v['system_prompt'] ?? '')) === '') {
            throw new DomainException('The system prompt cannot be empty.');
        }

        $keys = [];
        foreach ($v['input_schema']['fields'] ?? [] as $field) {
            if (! preg_match('/^[a-z][a-z0-9_]{1,40}$/', $field['key'] ?? '')) {
                throw new DomainException('Field keys must be snake_case.');
            }
            if (! in_array($field['type'] ?? '', ['text', 'number', 'select', 'multiselect'], true)) {
                throw new DomainException('Unknown field type: '.($field['type'] ?? '?'));
            }
            if (isset($keys[$field['key']])) {
                throw new DomainException('Duplicate field key: '.$field['key']);
            }
            $keys[$field['key']] = true;
        }

        $providers = $v['routing']['providers'] ?? [];
        if ($providers === [] || AiProvider::query()->whereIn('key', $providers)->count() !== count(array_unique($providers))) {
            throw new DomainException('Routing must list known providers.');
        }
        if (($v['credits_cost'] ?? 0) < 1) {
            throw new DomainException('Credit cost must be at least 1.');
        }
    }
}
