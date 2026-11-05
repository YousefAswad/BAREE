<?php

use App\Jobs\ImportUsersJob;
use App\Models\Department;
use App\Models\Invitation;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Institution\UserImportService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    seedRoles();
    Storage::fake('local');
    Notification::fake();
    $this->tenant = Tenant::factory()->create();
    $this->admin = userWithRole('institution_admin', $this->tenant);
    bareeTenantContext()->set($this->tenant->id);
    Department::create(['name' => 'الرياضيات']);
    User::factory()->forTenant($this->tenant)->create(['email' => 'exists@school.ps']);
});

function csv(string $body): UploadedFile
{
    return UploadedFile::fake()->createWithContent('staff.csv', "\xEF\xBB\xBF".$body);
}

it('validates every row before inviting anyone', function () {
    $file = csv("الاسم,البريد,القسم\nأحمد,ahmad@school.ps,الرياضيات\nمكرر,ahmad@school.ps,الرياضيات\nموجود,exists@school.ps,\nخطأ,not-an-email,\nقسم,x@school.ps,الفيزياء\n");

    $import = app(UserImportService::class)->stage($file, $this->admin);
    $problems = collect($import->report)->pluck('problems', 'row');

    expect($import->total)->toBe(5)->and($import->valid)->toBe(1)
        ->and($problems[3])->toContain('duplicate_in_file')
        ->and($problems[4])->toContain('already_a_user')
        ->and($problems[5])->toContain('invalid_email')
        ->and($problems[6])->toContain('unknown_department');
});

it('neutralises spreadsheet formula injection', function () {
    $rows = app(UserImportService::class)->parse("name,email\n=HYPERLINK(\"x\"),safe@school.ps\n");

    expect($rows[0]['name'])->toBe('HYPERLINK("x")');
});

it('queues the commit and only invites valid rows', function () {
    Queue::fake();
    $import = app(UserImportService::class)->stage(csv("name,email\nA,a1@school.ps\nB,bad\n"), $this->admin);

    app(UserImportService::class)->commit($import, $this->admin);
    Queue::assertPushed(ImportUsersJob::class);

    (new ImportUsersJob($import->id, $this->admin->id))->handle(bareeTenantContext(), app(\App\Services\Institution\InvitationService::class));

    expect(Invitation::count())->toBe(1)->and($import->fresh()->invited)->toBe(1);
});
