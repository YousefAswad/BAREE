<?php

namespace App\Http\Controllers\Studio;

use App\AI\Exceptions\AiPolicyViolation;
use App\Http\Controllers\Controller;
use App\Models\Output;
use App\Models\Workspace;
use App\Services\Studio\ScenarioService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class ScenarioController extends Controller
{
    public function __construct(private ScenarioService $scenarios) {}

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'workspace_id' => ['required', 'integer'], 'title' => ['required', 'string', 'max:160'],
            'grade' => ['nullable', 'string', 'max:40'], 'subject' => ['nullable', 'string', 'max:60'],
            'topic' => ['required', 'string', 'max:300'], 'objective' => ['nullable', 'string', 'max:500'],
        ]);
        $workspace = Workspace::query()->findOrFail($data['workspace_id']);

        return $this->attempt(fn () => to_route('outputs.show', $this->scenarios->create($request->user(), $workspace, $data['title'], $data)));
    }

    public function addScene(Request $request, Output $output): RedirectResponse
    {
        $data = $request->validate(['type' => ['required', Rule::in(ScenarioService::SCENE_TYPES)], 'title' => ['required', 'string', 'max:120'],
            'prompt' => ['required', 'string', 'max:2000'], 'style' => ['nullable', 'array']]);

        return $this->attempt(function () use ($request, $output, $data) {
            $this->scenarios->addScene($request->user(), $output, $data['type'], $data['title'], $data['prompt'], $data['style'] ?? []);

            return back();
        });
    }

    public function updateScene(Request $request, Output $output, string $scene): RedirectResponse
    {
        $data = $request->validate(['title' => ['sometimes', 'string', 'max:120'], 'prompt' => ['sometimes', 'string', 'max:2000'],
            'body' => ['sometimes', 'nullable', 'string', 'max:20000'], 'style' => ['sometimes', 'array']]);

        return $this->attempt(function () use ($request, $output, $scene, $data) {
            $this->scenarios->updateScene($request->user(), $output, $scene, $data);

            return back();
        });
    }

    public function reorder(Request $request, Output $output): RedirectResponse
    {
        $ids = $request->validate(['order' => ['required', 'array'], 'order.*' => ['string']])['order'];

        return $this->attempt(function () use ($request, $output, $ids) {
            $this->scenarios->reorder($request->user(), $output, $ids);

            return back();
        });
    }

    public function generate(Request $request, Output $output, string $scene): JsonResponse
    {
        try {
            return response()->json($this->scenarios->generateScene($request->user(), $output, $scene));
        } catch (AiPolicyViolation|DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function destroyScene(Request $request, Output $output, string $scene): RedirectResponse
    {
        return $this->attempt(function () use ($request, $output, $scene) {
            $this->scenarios->removeScene($request->user(), $output, $scene);

            return back();
        });
    }

    private function attempt(callable $action): RedirectResponse
    {
        try {
            return $action();
        } catch (DomainException|AiPolicyViolation $e) {
            return back()->withErrors(['scenario' => $e->getMessage()]);
        }
    }
}
