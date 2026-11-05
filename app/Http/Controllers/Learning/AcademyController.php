<?php

namespace App\Http\Controllers\Learning;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\CourseLesson;
use App\Models\Enrollment;
use App\Models\LessonProgress;
use App\Services\Export\HtmlRenderer;
use App\Services\Learning\CertificateService;
use App\Services\Learning\CourseService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;
use Inertia\Response;

final class AcademyController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $enrollments = Enrollment::query()->where('user_id', $user->id)->get()->keyBy('course_id');

        return Inertia::render('Academy/Index', [
            'courses' => Course::query()->where('status', 'published')->get()
                ->filter(fn ($c) => isset($enrollments[$c->id]) || empty($c->role_templates) || in_array($user->roleTemplateKey(), $c->role_templates, true))
                ->map(fn ($c) => ['key' => $c->key, 'title' => $c->title, 'description' => $c->description, 'hours' => $c->hours, 'certificate' => $c->certificate,
                    'source' => $c->tenant_id ? 'institution' : 'baree', 'progress' => $enrollments[$c->id]->progress ?? null,
                    'mandatory' => (bool) ($enrollments[$c->id]->mandatory ?? false), 'due_at' => $enrollments[$c->id]->due_at ?? null])->values(),
            'certificates' => Certificate::query()->where('user_id', $user->id)->whereNull('revoked_at')->get(['serial', 'course_id', 'issued_at', 'hours']),
            'hours' => (float) Certificate::query()->where('user_id', $user->id)->whereNull('revoked_at')->sum('hours'),
        ]);
    }

    public function show(Request $request, string $key): Response
    {
        $course = Course::query()->where('key', $key)->where('status', 'published')->with('modules.lessons')->firstOrFail();
        $done = LessonProgress::query()->where('user_id', $request->user()->id)->whereNotNull('completed_at')->pluck('lesson_id')->flip();

        return Inertia::render('Academy/Course', [
            'course' => $course->only(['key', 'title', 'description', 'hours', 'certificate']),
            'modules' => $course->modules->map(fn ($m) => ['title' => $m->title, 'lessons' => $m->lessons->map(fn (CourseLesson $l) => [
                'id' => $l->id, 'type' => $l->type, 'title' => $l->title, 'minutes' => $l->minutes, 'body' => $l->body, 'video_url' => $l->video_url,
                'quiz' => $l->publicQuiz(), 'mission' => $l->mission_id ? \App\Models\Mission::query()->whereKey($l->mission_id)->value('key') : null,
                'done' => isset($done[$l->id])])]),
            'progress' => Enrollment::query()->where('user_id', $request->user()->id)->where('course_id', $course->id)->value('progress') ?? 0,
        ]);
    }

    public function complete(Request $request, CourseLesson $lesson, CourseService $courses): JsonResponse
    {
        $answers = $request->validate(['answers' => ['nullable', 'array', 'max:50'], 'answers.*' => ['integer', 'between:0,10']])['answers'] ?? [];

        try {
            return response()->json($courses->completeLesson($request->user(), $lesson, $answers));
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function verify(string $serial, CertificateService $certificates): Response
    {
        return Inertia::render('Certificates/Verify', ['serial' => $serial, 'result' => $certificates->verify($serial)]);
    }

    public function download(Request $request, string $serial)
    {
        $certificate = Certificate::query()->where('serial', $serial)->where('user_id', $request->user()->id)->firstOrFail();
        $course = Course::query()->findOrFail($certificate->course_id);
        $markdown = "**{$certificate->holder_name}**\n\nأتمّ بنجاح: **".($course->title['ar'] ?? '')."**\n\nساعات التطوير المهني: {$certificate->hours}\n\nالرقم: `{$certificate->serial}`\n\nللتحقّق: ".url('/certificates/'.$certificate->serial);
        $html = app(HtmlRenderer::class)->render('شهادة إتمام', $markdown, 'BAREE');

        $pdf = Http::timeout(60)->attach('files', $html, 'index.html')
            ->post(rtrim((string) config('services.gotenberg.url'), '/').'/forms/chromium/convert/html')->throw()->body();

        return response($pdf, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="certificate-'.$certificate->serial.'.pdf"']);
    }
}
