<?php

require_once __DIR__.'/Phase5Helpers.php';

use App\Models\Passport;
use App\Models\Post;
use App\Models\PostView;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Content\FeedService;
use App\Services\Content\PostService;

beforeEach(function () {
    $this->ahmad = opsSetup();
    $this->admin = User::where('email', 'admin@future.test')->first();
    Passport::updateOrCreate(['user_id' => $this->ahmad->id], ['readiness' => 40, 'competencies' => ['responsible_ai' => 35]]);
    $this->posts = app(PostService::class);
});

it('targets posts by role, competency gap and department, and keeps institutions apart', function () {
    $this->posts->save($this->admin, ['kind' => 'tip', 'title' => 'للمعلّمين', 'status' => 'published', 'targeting' => ['roles' => ['teacher']]]);
    $this->posts->save($this->admin, ['kind' => 'tip', 'title' => 'للموارد البشرية', 'status' => 'published', 'targeting' => ['roles' => ['hr']]]);
    $this->posts->save($this->admin, ['kind' => 'video', 'title' => 'فجوة الاستخدام المسؤول', 'status' => 'published', 'targeting' => ['gap' => ['competency' => 'responsible_ai', 'below' => 60]]]);
    $this->posts->save($this->admin, ['kind' => 'news', 'title' => 'مسودة', 'status' => 'draft']);
    bareeTenantContext()->runAs(Tenant::factory()->create()->id, fn () => Post::create(['kind' => 'news', 'title' => ['ar' => 'مؤسسة ثانية'], 'status' => 'published', 'published_at' => now(), 'author_id' => $this->admin->id]));
    bareeTenantContext()->asPlatform(fn () => Post::create(['tenant_id' => null, 'kind' => 'tip', 'title' => ['ar' => 'نصيحة BAREE'], 'status' => 'published', 'published_at' => now(), 'author_id' => $this->admin->id]));

    $titles = collect(app(FeedService::class)->forUser($this->ahmad)['posts'])->pluck('title.ar');

    expect($titles)->toContain('للمعلّمين')->toContain('فجوة الاستخدام المسؤول')->toContain('نصيحة BAREE')
        ->not->toContain('للموارد البشرية')->not->toContain('مسودة')->not->toContain('مؤسسة ثانية')
        ->and(PostView::where('user_id', $this->ahmad->id)->count())->toBe(3);
});

it('strips unsafe call-to-action links', function () {
    $post = $this->posts->save($this->admin, ['kind' => 'tool', 'title' => 'x', 'status' => 'published', 'cta_url' => 'javascript:alert(1)']);
    $internal = $this->posts->save($this->admin, ['kind' => 'tool', 'title' => 'y', 'status' => 'published', 'cta_url' => '/studio/tools/worksheet']);

    expect($post->cta_url)->toBeNull()->and($internal->cta_url)->toBe('/studio/tools/worksheet');
});

it('estimates reach and refuses clicks on posts the user is not targeted by', function () {
    $hrPost = $this->posts->save($this->admin, ['kind' => 'tip', 'title' => 'hr', 'status' => 'published', 'targeting' => ['roles' => ['hr']]]);

    expect($this->posts->estimate($this->ahmad->tenant_id, ['roles' => ['teacher']]))->toBe(1);
    $this->actingAs($this->ahmad)->postJson("/posts/{$hrPost->id}/click")->assertNotFound();
});
