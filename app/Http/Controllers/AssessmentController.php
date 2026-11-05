<?php

namespace App\Http\Controllers;

use App\Domain\Assessment\AssessmentService;
use App\Http\Requests\AnswerQuestionRequest;
use App\Models\AssessmentAttempt;
use App\Models\AssessmentTemplate;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class AssessmentController extends Controller
{
    public function __construct(private AssessmentService $service) {}

    public function start(Request $request, AssessmentTemplate $template): RedirectResponse
    {
        $this->authorize('assessments.take');
        $attempt = $this->service->start($request->user(), $template);

        return to_route('assessments.show', $attempt);
    }

    public function show(Request $request, AssessmentAttempt $attempt): Response
    {
        $this->ensureOwner($request, $attempt);

        if ($attempt->status === 'scored') {
            return Inertia::render('Assessment/Result', $this->resultPayload($attempt));
        }

        $attempt->load(['template.items.question.options', 'template.items.question.competency', 'answers']);
        $answered = $attempt->answers->keyBy('question_id');

        return Inertia::render('Assessment/Take', [
            'attempt' => ['id' => $attempt->public_id, 'startedAt' => $attempt->started_at, 'timeLimit' => $attempt->template->time_limit_minutes],
            'template' => ['name' => $attempt->template->name, 'confidence' => (bool) data_get($attempt->template->settings, 'confidence', true)],
            // Options are sent without scores (hidden attribute); sections drive the category cards.
            'sections' => $attempt->template->items->groupBy('section')->map(fn ($items, $section) => [
                'key' => $section,
                'questions' => $items->map(fn ($item) => [
                    'id' => $item->question->id,
                    'type' => $item->question->type,
                    'competency' => $item->question->competency->name,
                    'stem' => $item->question->stem,
                    'scenario' => $item->question->scenario,
                    'options' => $item->question->options->map(fn ($o) => ['id' => $o->id, 'label' => $o->label]),
                    'answer' => optional($answered->get($item->question->id), fn ($a) => ['optionId' => $a->option_id, 'confidence' => $a->confidence]),
                ])->values(),
            ])->values(),
        ]);
    }

    public function answer(AnswerQuestionRequest $request, AssessmentAttempt $attempt): RedirectResponse
    {
        $this->ensureOwner($request, $attempt);

        try {
            $this->service->answer($attempt, $request->integer('question_id'), $request->integer('option_id') ?: null,
                $request->integer('confidence') ?: null, $request->integer('time_ms') ?: null);
        } catch (DomainException $e) {
            return back()->withErrors(['answer' => $e->getMessage()]);
        }

        return back();
    }

    public function submit(Request $request, AssessmentAttempt $attempt): RedirectResponse
    {
        $this->ensureOwner($request, $attempt);

        try {
            $this->service->submit($attempt);
        } catch (DomainException $e) {
            return back()->withErrors(['submit' => $e->getMessage()]);
        }

        return to_route('assessments.show', $attempt);
    }

    private function ensureOwner(Request $request, AssessmentAttempt $attempt): void
    {
        // Tenant scope already hides other institutions (404). This blocks colleagues inside the same one.
        abort_unless($attempt->user_id === $request->user()->id, 403);
    }

    private function resultPayload(AssessmentAttempt $attempt): array
    {
        $attempt->load(['level', 'template.framework.competencies', 'template.framework.levels']);
        $framework = $attempt->template->framework;

        return [
            'result' => $attempt->result,
            'level' => $attempt->level?->only(['rank', 'key', 'name', 'color']),
            'levels' => $framework->levels->map->only(['id', 'rank', 'key', 'name', 'min_score', 'color']),
            'competencies' => $framework->competencies->map->only(['key', 'name']),
        ];
    }
}
