<?php

namespace App\Services\Studio;

use App\AI\Gateway\AiGateway;
use App\Models\MediaJob;
use App\Models\Output;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Media\MediaService;
use App\Support\Audit;
use DomainException;
use Illuminate\Support\Str;

/**
 * A learning scenario is an output of type "scenario": an ordered list of scenes (text, image, voice,
 * video, 3D, quiz, group activity). Text-like scenes are written by an agent; media scenes go through
 * MediaService (moderation, credits, privacy). Scenes are edited in place; "save version" snapshots.
 */
final class ScenarioService
{
    public const SCENE_TYPES = ['text', 'image', 'voice', 'video', 'model3d', 'quiz', 'activity'];

    private const MEDIA_TYPE = ['image' => 'image', 'voice' => 'speech', 'video' => 'video', 'model3d' => 'model3d'];

    public const MAX_SCENES = 20;

    public function __construct(private OutputService $outputs, private WorkspaceService $workspaces, private AiGateway $gateway, private MediaService $media) {}

    public function create(User $user, Workspace $workspace, string $title, array $context): Output
    {
        if (! in_array($this->workspaces->access($user, $workspace), ['owner', 'editor'], true)) {
            throw new DomainException(__('You cannot create items here.'));
        }

        return Output::create([
            'workspace_id' => $workspace->id, 'type' => 'scenario', 'title' => mb_substr($title, 0, 190), 'version' => 1, 'created_by' => $user->id,
            'content' => ['format' => 'scenario', 'context' => array_intersect_key($context, array_flip(['grade', 'subject', 'topic', 'objective'])), 'scenes' => []],
            'metadata' => ['ai_generated' => true],
        ]);
    }

    public function addScene(User $user, Output $scenario, string $type, string $title, string $prompt, array $style = []): array
    {
        $this->assertEditable($user, $scenario);
        if (! in_array($type, self::SCENE_TYPES, true)) {
            throw new DomainException(__('Unknown scene type.'));
        }
        $content = $scenario->content;
        if (count($content['scenes']) >= self::MAX_SCENES) {
            throw new DomainException(__('A scenario can have up to :n scenes.', ['n' => self::MAX_SCENES]));
        }

        $scene = ['id' => (string) Str::ulid(), 'type' => $type, 'title' => mb_substr($title, 0, 120), 'prompt' => mb_substr($prompt, 0, 2000),
            'style' => array_intersect_key($style, array_flip(['visual', 'duration', 'voice', 'difficulty'])), 'body' => null, 'media_job_id' => null, 'status' => 'draft'];
        $content['scenes'][] = $scene;
        $scenario->update(['content' => $content]);

        return $scene;
    }

    public function updateScene(User $user, Output $scenario, string $sceneId, array $changes): void
    {
        $this->assertEditable($user, $scenario);
        $this->mutate($scenario, $sceneId, fn ($scene) => array_merge($scene, array_intersect_key($changes, array_flip(['title', 'prompt', 'body', 'style']))));
    }

    public function reorder(User $user, Output $scenario, array $orderedIds): void
    {
        $this->assertEditable($user, $scenario);
        $content = $scenario->content;
        $byId = collect($content['scenes'])->keyBy('id');
        if ($byId->keys()->sort()->values()->all() !== collect($orderedIds)->sort()->values()->all()) {
            throw new DomainException(__('The new order must contain every scene exactly once.'));
        }
        $content['scenes'] = array_map(fn ($id) => $byId[$id], $orderedIds);
        $scenario->update(['content' => $content]);
    }

    public function removeScene(User $user, Output $scenario, string $sceneId): void
    {
        $this->assertEditable($user, $scenario);
        $content = $scenario->content;
        $content['scenes'] = array_values(array_filter($content['scenes'], fn ($s) => $s['id'] !== $sceneId));
        $scenario->update(['content' => $content]);
    }

    public function generateScene(User $user, Output $scenario, string $sceneId): array
    {
        $this->assertEditable($user, $scenario);
        $scene = collect($scenario->content['scenes'])->firstWhere('id', $sceneId) ?? throw new DomainException(__('Scene not found.'));
        $context = $scenario->content['context'] ?? [];

        if (isset(self::MEDIA_TYPE[$scene['type']])) {
            $prompt = trim(($context['topic'] ?? '').'. '.$scene['prompt'].'. '.($scene['style']['visual'] ?? ''));
            $job = $this->media->request($user, self::MEDIA_TYPE[$scene['type']], $scene['type'] === 'voice' ? ($scene['body'] ?: $scene['prompt']) : $prompt, $scenario->workspace,
                array_filter(['voice' => $scene['style']['voice'] ?? null, 'duration' => $scene['style']['duration'] ?? null]));

            return $this->mutate($scenario, $sceneId, fn ($s) => array_merge($s, ['media_job_id' => $job->id, 'status' => 'generating']));
        }

        $run = $this->gateway->run($user, 'scenario_scene', [
            'scene_type' => $scene['type'], 'grade' => (string) ($context['grade'] ?? ''), 'subject' => (string) ($context['subject'] ?? ''),
            'topic' => (string) ($context['topic'] ?? $scenario->title), 'request' => $scene['prompt'],
        ], $scenario->workspace);

        return $this->mutate($scenario, $sceneId, fn ($s) => array_merge($s, ['body' => $this->gateway->presentOutput($run, $user), 'status' => 'ready']));
    }

    /** Scenes enriched with live media status for the editor and the player. */
    public function present(Output $scenario): array
    {
        $jobs = MediaJob::query()->whereIn('id', collect($scenario->content['scenes'])->pluck('media_job_id')->filter())->get()->keyBy('id');

        return array_map(function ($scene) use ($jobs) {
            $job = $scene['media_job_id'] ? $jobs[$scene['media_job_id']] ?? null : null;
            if ($job) {
                $scene['status'] = match ($job->status) { 'succeeded' => 'ready', 'failed', 'blocked' => 'failed', default => 'generating' };
                $scene['media'] = ['id' => $job->public_id, 'mime' => $job->mime, 'status' => $job->status];
            }

            return $scene;
        }, $scenario->content['scenes']);
    }

    private function mutate(Output $scenario, string $sceneId, callable $change): array
    {
        $content = $scenario->content;
        $updated = null;
        foreach ($content['scenes'] as $i => $scene) {
            if ($scene['id'] === $sceneId) {
                $content['scenes'][$i] = $updated = $change($scene);
            }
        }
        if ($updated === null) {
            throw new DomainException(__('Scene not found.'));
        }
        $scenario->update(['content' => $content]);
        Audit::log('scenario.scene_updated', $scenario, ['scene' => $sceneId]);

        return $updated;
    }

    private function assertEditable(User $user, Output $scenario): void
    {
        if ($scenario->type !== 'scenario') {
            throw new DomainException('Not a scenario.');
        }
        $this->outputs->assertCanEdit($scenario, $user);
    }
}
