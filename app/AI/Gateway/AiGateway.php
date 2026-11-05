<?php

namespace App\AI\Gateway;

use App\AI\Context\ContextBuilder;
use App\AI\Contracts\StreamingLlmDriver;
use App\AI\Data\LlmRequest;
use App\AI\Data\LlmResponse;
use App\AI\Exceptions\AiPolicyViolation;
use App\AI\Exceptions\ProviderUnavailable;
use App\AI\Privacy\PiiRedactor;
use App\AI\ProviderRegistry;
use App\AI\VersionResolver;
use App\Models\Agent;
use App\Models\AgentVersion;
use App\Models\AiRun;
use App\Models\TenantAgentSetting;
use App\Models\User;
use App\Models\Workspace;
use App\Services\CreditService;
use App\Services\Institution\ProtectedTermsRepository;
use App\Support\Audit;
use Illuminate\Support\Facades\Validator;
use Throwable;

/**
 * Every AI call in BAREE goes through here. One place for: authorisation, input validation, credits,
 * layered context + documents, privacy redaction, provider routing with failover (optionally streaming),
 * metering and audit.
 */
final class AiGateway
{
    public function __construct(
        private ProviderRegistry $providers,
        private ContextBuilder $context,
        private PiiRedactor $redactor,
        private CreditService $credits,
        private VersionResolver $versions,
        private ProtectedTermsRepository $terms,
    ) {}

    /** Single-shot agent run. */
    public function run(User $user, string $agentKey, array $inputs, ?Workspace $workspace = null, ?string $taskInstructions = null, array $fileIds = [], ?callable $onDelta = null, array $extraDocuments = []): AiRun
    {
        [$agent, $version] = $this->resolve($user, $agentKey);

        return $this->execute($user, $agent, $version, $inputs, $workspace, $taskInstructions, $fileIds, $onDelta, null, null, $extraDocuments);
    }

    /** One step of a multi-step workflow (see WorkflowRunner). */
    public function runStep(User $user, Agent $agent, AgentVersion $version, array $step, array $inputs, ?Workspace $workspace, ?string $taskInstructions, array $fileIds, int $workflowRunId, ?callable $onDelta = null): AiRun
    {
        return $this->execute($user, $agent, $version, $inputs, $workspace, $taskInstructions, $fileIds, $onDelta, $step, $workflowRunId);
    }

    /** @return array{0: Agent, 1: AgentVersion} */
    public function resolve(User $user, string $agentKey): array
    {
        $agent = Agent::query()->where('key', $agentKey)->where('status', 'published')->where('availability', 'available')
            ->firstOr(fn () => throw new AiPolicyViolation('Agent is not available.'));
        $version = $this->versions->forTenant($agent, $user->tenant_id) ?? throw new AiPolicyViolation('Agent has no published version.');

        return [$agent, $version];
    }

    private function execute(User $user, Agent $agent, AgentVersion $version, array $inputs, ?Workspace $workspace, ?string $taskInstructions, array $fileIds, ?callable $onDelta, ?array $step = null, ?int $workflowRunId = null, array $extraDocuments = []): AiRun
    {
        $this->authorize($user, $agent);
        $inputs = $this->validateInputs($version->input_schema, $inputs);
        $cost = (int) ($step['credits'] ?? $version->credits_cost);
        $this->credits->ensureCanSpend($user->tenant_id, $cost, $agent);

        $built = $this->context->build($version, $user, $workspace, $taskInstructions, $step['prompt'] ?? null);
        $protected = array_merge((array) ($user->tenant?->setting('privacy.protected_names') ?? []), $this->terms->forTenant($user->tenant_id));

        $documents = array_merge($this->context->documents($version, $user, $workspace, $fileIds), $extraDocuments);
        $userText = trim($this->context->renderDocuments($documents)."\n\n".$this->context->renderInputs($version, $inputs));

        $system = $this->redactor->redact($built['system'], $protected);
        $message = $this->redactor->redact($userText, $protected, $system['map']);

        $run = AiRun::create([
            'user_id' => $user->id,
            'agent_version_id' => $version->id,
            'workspace_id' => $workspace?->id,
            'workflow_run_id' => $workflowRunId,
            'step_key' => $step['key'] ?? null,
            'status' => 'running',
            'inputs' => ['fields' => array_keys($inputs), 'layers' => $built['layers'], 'documents' => count($documents)],
            'redaction_map' => $message['map'],
            'redactions_count' => $system['count'] + $message['count'],
        ]);

        $started = hrtime(true);
        [$response, $trail] = $this->callWithFailover($step['routing'] ?? $version->routing, $user->tenant_id, $system['text'], $message['text'], $run, $onDelta);

        $run->update([
            'status' => 'succeeded',
            'provider' => $response->provider,
            'model' => $response->model,
            'output' => $response->text, // stays redacted at rest
            'input_tokens' => $response->inputTokens,
            'output_tokens' => $response->outputTokens,
            'credits' => $cost,
            'latency_ms' => (int) ((hrtime(true) - $started) / 1_000_000),
            'attempts' => $trail,
        ]);

        $this->credits->debit($user->tenant_id, $cost, $run, $agent->id, $user->id);
        Audit::log('ai.run', $run, ['agent' => $agent->key, 'version' => $version->version, 'step' => $step['key'] ?? null, 'provider' => $response->provider, 'redactions' => $run->redactions_count]);

        return $run;
    }

    /**
     * Run a specific version with platform keys and no tenant data, for BAREE's test suite.
     * No credits, no ai_runs row; the caller records the result.
     *
     * @return array{text:string, provider:string, latency_ms:int}
     */
    public function runSandbox(AgentVersion $version, array $inputs, ?string $taskInstructions = null): array
    {
        $inputs = $this->validateInputs($version->input_schema, $inputs);
        $built = $this->context->build($version, null, null, $taskInstructions);
        $documents = $this->context->documents($version, null, null);
        $message = $this->redactor->redact(trim($this->context->renderDocuments($documents)."\n\n".$this->context->renderInputs($version, $inputs)));

        $started = hrtime(true);
        [$response] = $this->callWithFailover($version->routing, null, $built['system'], $message['text']);

        return [
            'text' => $this->redactor->restore($response->text, $message['map']),
            'provider' => $response->provider,
            'latency_ms' => (int) ((hrtime(true) - $started) / 1_000_000),
        ];
    }

    /** Output with real names restored — only ever called for the run's owner. */
    public function presentOutput(AiRun $run, User $viewer): string
    {
        abort_unless($run->user_id === $viewer->id, 403);

        return $this->redactor->restore((string) $run->output, $run->redaction_map ?? []);
    }

    /** @return array{0: LlmResponse, 1: array} */
    private function callWithFailover(array $routing, ?int $tenantId, string $system, string $message, ?AiRun $run = null, ?callable $onDelta = null): array
    {
        $trail = [];

        foreach ($routing['providers'] ?? [] as $providerKey) {
            $streamed = false;
            try {
                $driver = $this->providers->driver($providerKey, $tenantId);
                $request = new LlmRequest(
                    system: $system,
                    messages: [['role' => 'user', 'content' => $message]],
                    model: $this->providers->modelFor($providerKey, $routing['tier'] ?? 'standard'),
                    maxTokens: (int) ($routing['max_tokens'] ?? 4000),
                );

                if ($onDelta && $driver instanceof StreamingLlmDriver) {
                    $response = $driver->stream($request, function (string $piece) use ($onDelta, &$streamed) {
                        $streamed = true;
                        $onDelta($piece);
                    });
                } else {
                    $response = $driver->send($request);
                }
            } catch (ProviderUnavailable $e) {
                $trail[] = ['provider' => $providerKey, 'error' => $e->getMessage()];
                if ($streamed) {
                    break; // text already reached the user: do not silently switch provider mid-answer
                }

                continue;
            } catch (Throwable $e) {
                $trail[] = ['provider' => $providerKey, 'error' => class_basename($e)];
                $run?->update(['status' => 'failed', 'attempts' => $trail, 'error' => 'provider_error']);
                report($e);

                throw $e;
            }

            $trail[] = ['provider' => $providerKey, 'ok' => true];

            return [$response, $trail];
        }

        $run?->update(['status' => 'failed', 'attempts' => $trail, 'error' => 'all_providers_unavailable']);

        throw new ProviderUnavailable('All providers are unavailable right now.');
    }

    private function authorize(User $user, Agent $agent): void
    {
        if (! $user->hasPermission('ai.agents.use')) {
            throw new AiPolicyViolation('You do not have access to AI agents.');
        }

        $setting = TenantAgentSetting::query()->where('agent_id', $agent->id)->first();
        if ($setting && ! $setting->enabled) {
            throw new AiPolicyViolation('This agent is disabled by your institution.');
        }
        if ($setting && $setting->department_ids && ! in_array($user->department_id, $setting->department_ids, true)) {
            throw new AiPolicyViolation('This agent is not enabled for your department.');
        }

        $allowedRoles = $agent->role_templates;
        if ($allowedRoles && ! in_array($user->roleTemplateKey(), $allowedRoles, true)) {
            throw new AiPolicyViolation('This agent is not available for your role.');
        }
    }

    public function validateInputs(array $schema, array $inputs): array
    {
        $rules = [];
        foreach ($schema['fields'] ?? [] as $field) {
            $rule = [($field['required'] ?? false) ? 'required' : 'nullable'];
            $rule[] = match ($field['type'] ?? 'text') {
                'number' => 'numeric',
                'multiselect' => 'array',
                'select' => 'in:'.implode(',', array_column($field['options'] ?? [], 'value')),
                default => 'string',
            };
            if (($field['type'] ?? 'text') === 'text') {
                $rule[] = 'max:'.($field['max'] ?? 2000);
            }
            $rules[$field['key']] = $rule;
        }

        $validator = Validator::make($inputs, $rules);
        if ($validator->fails()) {
            throw new AiPolicyViolation('Invalid inputs: '.implode(' ', $validator->errors()->all()));
        }

        return $validator->validated();
    }
}
