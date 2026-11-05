<?php

require_once __DIR__.'/StudioTestHelpers.php';

use App\AI\Exceptions\AiPolicyViolation;
use App\Models\Export;
use App\Models\MediaJob;
use App\Models\Output;
use App\Models\Plan;
use App\Services\CreditService;
use App\Services\Export\ExportService;
use App\Services\Institution\ProtectedTermsRepository;
use App\Services\Media\Drivers\AsyncHttpDriver;
use App\Services\Media\Drivers\FakeMediaDriver;
use App\Services\Media\MediaRegistry;
use App\Services\Media\MediaService;

beforeEach(function () {
    $this->ahmad = studioSetup();
    $this->fake = new FakeMediaDriver;
    foreach (['image', 'speech', 'video', 'model3d'] as $type) {
        app(MediaRegistry::class)->override($type, $this->fake);
    }
});

it('generates an image, stores it privately and charges only on success', function () {
    $before = app(CreditService::class)->balance($this->ahmad->tenant_id);

    $job = app(MediaService::class)->request($this->ahmad, 'image', 'رسم توضيحي لدورة الماء للصف الرابع');

    expect($job->fresh()->status)->toBe('succeeded')
        ->and(\Illuminate\Support\Facades\Storage::disk('local')->exists($job->fresh()->path))->toBeTrue()
        ->and(app(CreditService::class)->balance($this->ahmad->tenant_id))->toBe($before - 4);
});

it('blocks media that would depict a real student, without charging', function () {
    app(ProtectedTermsRepository::class)->add(['رهف']);
    $before = app(CreditService::class)->balance($this->ahmad->tenant_id);

    expect(fn () => app(MediaService::class)->request($this->ahmad, 'image', 'صورة لرهف وهي بتحل مسألة'))->toThrow(AiPolicyViolation::class)
        ->and(MediaJob::latest('id')->first()->status)->toBe('blocked')
        ->and(app(CreditService::class)->balance($this->ahmad->tenant_id))->toBe($before)
        ->and($this->fake->prompts)->toBe([]);
});

it('polls async video jobs until the provider finishes', function () {
    $this->fake->pollsUntilDone = 3;

    $job = app(MediaService::class)->request($this->ahmad, 'video', 'رسوم متحركة تشرح البناء الضوئي');

    expect($job->fresh()->status)->toBe('succeeded')->and($job->fresh()->polls)->toBe(3);
});

it('refuses to download provider results from hosts that are not allow-listed', function () {
    $driver = new AsyncHttpDriver(['allowed_hosts' => ['cdn.provider.com']], 'video/mp4', 'mp4');

    expect(fn () => $driver->download('https://169.254.169.254/latest/meta-data'))->toThrow(RuntimeException::class)
        ->and(fn () => $driver->download('http://cdn.provider.com/x.mp4'))->toThrow(RuntimeException::class);
});

it('respects plans without advanced media', function () {
    $plan = bareeTenantContext()->asPlatform(fn () => Plan::where('key', 'starter')->first());
    $this->ahmad->tenant->update(['plan_id' => $plan->id]);

    expect(fn () => app(MediaService::class)->request($this->ahmad->fresh(), 'video', 'فيديو'))->toThrow(AiPolicyViolation::class);
});

it('exports to HTML and Markdown with the AI disclosure, downloadable only by its owner', function () {
    $workspace = workspaceFor($this->ahmad);
    $output = Output::create(['workspace_id' => $workspace->id, 'type' => 'lesson_plan', 'title' => 'خطة المعادلات', 'version' => 1,
        'content' => ['format' => 'markdown', 'body' => "## الأهداف\n\n- يحل المعادلة"]]);

    $html = app(ExportService::class)->request($this->ahmad, $output, 'html');
    $export = Export::find($html->id);
    $stored = \Illuminate\Support\Facades\Storage::disk('local')->get($export->path);

    expect($export->status)->toBe('ready')->and($stored)->toContain('dir="rtl"')->toContain('بمساعدة الذكاء الاصطناعي');

    $this->actingAs($this->ahmad)->get("/exports/{$export->public_id}")->assertOk();
    $this->actingAs(\App\Models\User::where('email', 'admin@future.test')->first())->get("/exports/{$export->public_id}")->assertNotFound();
});

it('exports Word files right-to-left when PhpWord is installed', function () {
    if (! class_exists(\PhpOffice\PhpWord\PhpWord::class)) {
        $this->markTestSkipped('composer require phpoffice/phpword');
    }
    $bytes = app(\App\Services\Export\DocxRenderer::class)->render('عنوان', "## قسم\n\nنص", 'أحمد');

    expect(substr($bytes, 0, 2))->toBe('PK');
});
