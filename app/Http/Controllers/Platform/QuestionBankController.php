<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\AssessmentTemplate;
use App\Models\AssessmentTemplateItem;
use App\Models\Competency;
use App\Models\Question;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class QuestionBankController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('platform.frameworks.manage');

        return Inertia::render('Platform/Questions/Index', [
            'questions' => Question::query()->whereNull('tenant_id')->with(['options' => fn ($q) => $q->orderBy('position')])
                ->when($request->integer('competency'), fn ($q, $id) => $q->where('competency_id', $id))
                ->orderByDesc('id')->paginate(30)->withQueryString()
                ->through(fn (Question $q) => $q->only(['id', 'competency_id', 'type', 'difficulty', 'stem', 'scenario', 'explanation', 'audience', 'status'])
                    + ['options' => $q->options->map(fn ($o) => $o->only(['position', 'label', 'feedback']) + ['score' => $o->score])]), // staff only
            'competencies' => Competency::query()->get(['id', 'key', 'name']),
            'templates' => AssessmentTemplate::query()->whereNull('tenant_id')->withCount('items')->get(['id', 'key', 'name', 'status']),
            'filters' => $request->only('competency'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('platform.frameworks.manage');
        $data = $this->validated($request);

        DB::transaction(function () use ($data) {
            $question = Question::create(array_merge(collect($data)->except('options', 'template_id')->all(), ['tenant_id' => null, 'version' => 1]));
            foreach ($data['options'] as $i => $option) {
                $question->options()->create(['position' => $i + 1, 'label' => ['ar' => $option['label']], 'score' => $option['score'], 'feedback' => isset($option['feedback']) ? ['ar' => $option['feedback']] : null]);
            }
            if (! empty($data['template_id'])) {
                AssessmentTemplateItem::create(['template_id' => $data['template_id'], 'question_id' => $question->id, 'section' => $data['competency_id'],
                    'position' => (int) AssessmentTemplateItem::query()->where('template_id', $data['template_id'])->max('position') + 1]);
            }
            Audit::log('question.created', $question);
        });

        return back();
    }

    public function retire(Question $question): RedirectResponse
    {
        $this->authorize('platform.frameworks.manage');
        $question->update(['status' => 'retired']); // keeps scoring history of past attempts intact

        return back();
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'competency_id' => ['required', 'exists:competencies,id'],
            'type' => ['required', 'in:scenario,mcq'],
            'difficulty' => ['required', 'integer', 'between:1,5'],
            'weight' => ['nullable', 'numeric', 'between:0.1,5'],
            'stem' => ['required', 'array'], 'stem.ar' => ['required', 'string', 'max:2000'],
            'scenario' => ['nullable', 'array'], 'scenario.ar' => ['nullable', 'string', 'max:4000'],
            'explanation' => ['nullable', 'array'],
            'audience' => ['nullable', 'array'],
            'status' => ['required', 'in:draft,published'],
            'options' => ['required', 'array', 'min:3', 'max:6'],
            'options.*.label' => ['required', 'string', 'max:600'],
            'options.*.score' => ['required', 'integer', 'between:0,3'],
            'options.*.feedback' => ['nullable', 'string', 'max:600'],
            'template_id' => ['nullable', 'exists:assessment_templates,id'],
        ]);

        // A good item has exactly one best answer and at least one clearly weak one.
        $scores = array_column($data['options'], 'score');
        if (count(array_keys($scores, 3)) !== 1 || min($scores) > 1) {
            throw ValidationException::withMessages(['options' => __('Each question needs exactly one best answer (3) and at least one weak option (0 or 1).')]);
        }

        return $data + ['weight' => $data['weight'] ?? 1];
    }
}
