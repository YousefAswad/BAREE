<?php

namespace App\Http\Controllers\Institution;

use App\Http\Controllers\Controller;
use App\Jobs\SyncSegmentMembersJob;
use App\Models\Segment;
use App\Models\User;
use App\Services\Institution\SegmentEvaluator;
use App\Support\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Inertia\Inertia;
use Inertia\Response;

final class SegmentController extends Controller
{
    public function index(): Response
    {
        $this->authorize('segments.manage');

        return Inertia::render('Institution/Segments/Index', [
            'segments' => Segment::query()->withCount('users')->orderBy('name')->get(['id', 'name', 'mode', 'rules']),
            'fields' => SegmentEvaluator::FIELDS,
        ]);
    }

    public function store(Request $request, SegmentEvaluator $evaluator): RedirectResponse
    {
        $this->authorize('segments.manage');
        $data = $this->validated($request, $evaluator);
        $segment = Segment::create($data);
        SyncSegmentMembersJob::dispatch($segment->tenant_id, $segment->id);
        Audit::log('segment.created', $segment);

        return back();
    }

    public function update(Request $request, Segment $segment, SegmentEvaluator $evaluator): RedirectResponse
    {
        $this->authorize('segments.manage');
        $segment->update($this->validated($request, $evaluator));
        SyncSegmentMembersJob::dispatch($segment->tenant_id, $segment->id);
        Audit::log('segment.updated', $segment);

        return back();
    }

    /** Live count while the admin builds the rules. */
    public function preview(Request $request, SegmentEvaluator $evaluator): JsonResponse
    {
        $this->authorize('segments.manage');
        $rules = $request->validate(['rules' => ['required', 'array']])['rules'];

        try {
            $evaluator->validate($rules);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $count = 0;
        User::query()->where('tenant_id', $request->user()->tenant_id)->with(['jobTitle', 'passport.level'])
            ->chunkById(500, function ($users) use ($evaluator, $rules, &$count) {
                $count += $users->filter(fn ($u) => $evaluator->matches($u, $rules))->count();
            });

        return response()->json(['count' => $count]);
    }

    private function validated(Request $request, SegmentEvaluator $evaluator): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'mode' => ['required', 'in:rules,manual'],
            'rules' => ['required_if:mode,rules', 'nullable', 'array'],
        ]);

        if ($data['mode'] === 'rules') {
            try {
                $evaluator->validate($data['rules']);
            } catch (InvalidArgumentException $e) {
                abort(422, $e->getMessage());
            }
        }

        return $data;
    }
}
