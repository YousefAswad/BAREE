<?php

namespace App\Services\Platform;

use App\AI\Gateway\AiGateway;
use App\Models\AgentTestCase;
use App\Models\AgentTestRun;
use App\Models\AgentVersion;
use App\Support\Audit;
use Illuminate\Support\Str;
use Throwable;

/** Runs an agent version against its fixed test cases with deterministic assertions. */
final class AgentEvaluationService
{
    public function __construct(private AiGateway $gateway) {}

    /** @return array{batch:string, passed:int, failed:int, errors:int, blocking_failed:int} */
    public function run(AgentVersion $version): array
    {
        $batch = (string) Str::ulid();
        $summary = ['batch' => $batch, 'passed' => 0, 'failed' => 0, 'errors' => 0, 'blocking_failed' => 0];

        $cases = AgentTestCase::query()->where('agent_id', $version->agent_id)->where('active', true)->get();

        foreach ($cases as $case) {
            try {
                $result = $this->gateway->runSandbox($version, $case->inputs, $case->task_instructions);
                $checks = $this->check($result['text'], $case->assertions);
                $status = collect($checks)->every(fn ($c) => $c['ok']) ? 'passed' : 'failed';
            } catch (Throwable $e) {
                $result = ['text' => null, 'provider' => null, 'latency_ms' => null];
                $checks = [['type' => 'execution', 'ok' => false, 'detail' => class_basename($e).': '.Str::limit($e->getMessage(), 200)]];
                $status = 'error';
            }

            AgentTestRun::create([
                'agent_version_id' => $version->id, 'test_case_id' => $case->id, 'batch' => $batch, 'status' => $status,
                'output' => $result['text'], 'checks' => $checks, 'provider' => $result['provider'], 'latency_ms' => $result['latency_ms'],
                'created_at' => now(),
            ]);

            $summary[$status === 'passed' ? 'passed' : ($status === 'failed' ? 'failed' : 'errors')]++;
            if ($status !== 'passed' && $case->blocking) {
                $summary['blocking_failed']++;
            }
        }

        Audit::log('agent.tests_run', $version, $summary);

        return $summary;
    }

    public function latestBatchPasses(AgentVersion $version): bool
    {
        $batch = AgentTestRun::query()->where('agent_version_id', $version->id)->orderByDesc('id')->value('batch');
        if ($batch === null) {
            return false;
        }

        $blockingIds = AgentTestCase::query()->where('agent_id', $version->agent_id)->where('active', true)->where('blocking', true)->pluck('id');
        $runs = AgentTestRun::query()->where('batch', $batch)->get()->keyBy('test_case_id');

        return $blockingIds->every(fn ($id) => ($runs[$id]->status ?? null) === 'passed');
    }

    /** @return array<int, array{type:string, ok:bool, detail?:string}> */
    public function check(string $output, array $assertions): array
    {
        return array_map(function (array $a) use ($output) {
            $value = $a['value'] ?? null;
            $ok = match ($a['type']) {
                'contains' => str_contains($output, (string) $value),
                'not_contains' => ! str_contains($output, (string) $value),
                'regex' => @preg_match((string) $value, $output) === 1,
                'max_chars' => mb_strlen($output) <= (int) $value,
                'min_chars' => mb_strlen($output) >= (int) $value,
                'sections' => collect((array) $value)->every(fn ($h) => str_contains($output, '## '.$h)),
                default => false,
            };

            return ['type' => $a['type'], 'value' => $value, 'ok' => $ok];
        }, $assertions);
    }
}
