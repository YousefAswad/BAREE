<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\CompetencyFramework;
use App\Services\Platform\FrameworkService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class FrameworkController extends Controller
{
    public function edit(): Response
    {
        $this->authorize('platform.frameworks.manage');
        $framework = $this->framework();

        return Inertia::render('Platform/Frameworks/Edit', [
            'framework' => $framework->only(['id', 'key', 'name', 'version']),
            'competencies' => $framework->competencies->map->only(['key', 'name', 'weight']),
            'levels' => $framework->levels->map->only(['rank', 'key', 'name', 'min_score', 'color', 'requirements']),
        ]);
    }

    public function preview(Request $request, FrameworkService $service): JsonResponse
    {
        $this->authorize('platform.frameworks.manage');
        [$weights, $levels] = $this->payload($request);

        try {
            return response()->json($service->preview($this->framework(), $weights, $levels));
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function update(Request $request, FrameworkService $service): RedirectResponse
    {
        $this->authorize('platform.frameworks.manage');
        [$weights, $levels] = $this->payload($request);
        $reason = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']])['reason'];

        try {
            $service->save($this->framework(), $weights, $levels, $reason);
        } catch (DomainException $e) {
            return back()->withErrors(['framework' => $e->getMessage()]);
        }

        return back()->with('status', __('Saved. Passports update on their next refresh.'));
    }

    private function framework(): CompetencyFramework
    {
        return CompetencyFramework::query()->whereNull('tenant_id')->where('is_default', true)->with(['competencies', 'levels'])->firstOrFail();
    }

    private function payload(Request $request): array
    {
        $data = $request->validate([
            'weights' => ['required', 'array'], 'weights.*' => ['numeric', 'between:0,100'],
            'levels' => ['required', 'array', 'min:2', 'max:10'],
            'levels.*.rank' => ['required', 'integer', 'between:1,10'],
            'levels.*.min_score' => ['required', 'numeric', 'between:0,100'],
            'levels.*.requirements' => ['nullable', 'array'],
        ]);

        return [array_map('floatval', $data['weights']), $data['levels']];
    }
}
