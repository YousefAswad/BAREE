<?php

require_once __DIR__.'/StudioTestHelpers.php';

use App\Models\KnowledgeFile;
use Illuminate\Http\UploadedFile;

beforeEach(fn () => $this->ahmad = studioSetup());

function docx(string $text, array $extra = []): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'docx');
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::OVERWRITE);
    $zip->addFromString('[Content_Types].xml', '<Types/>');
    $zip->addFromString('word/document.xml', "<w:document><w:body><w:p><w:r><w:t>{$text}</w:t></w:r></w:p></w:body></w:document>");
    foreach ($extra as $name => $content) {
        $zip->addFromString($name, $content);
    }
    $zip->close();

    return new UploadedFile($path, 'lesson.docx', null, null, true);
}

it('indexes a clean Word file so agents can read it', function () {
    $this->actingAs($this->ahmad)->post('/files', ['file' => docx('دليل المعلّم للوحدة الثالثة')])->assertCreated();

    $file = KnowledgeFile::latest('id')->first();
    expect($file->status)->toBe('indexed')->and($file->extracted_text)->toContain('دليل المعلّم');
});

it('rejects executables renamed to pdf and office files with macros', function () {
    $exe = UploadedFile::fake()->createWithContent('notes.pdf', "MZ\x90\x00".str_repeat("\x00", 200));
    $this->actingAs($this->ahmad)->postJson('/files', ['file' => $exe])->assertStatus(422);

    $this->actingAs($this->ahmad)->postJson('/files', ['file' => docx('x', ['word/vbaProject.bin' => 'macro'])])->assertStatus(422);
});

it('quarantines infected files and deletes the bytes', function () {
    $eicar = UploadedFile::fake()->createWithContent('test.txt', 'X5O!P%@AP[4\PZX54(P^)7CC)7}$EICAR-STANDARD-ANTIVIRUS-TEST-FILE!$H+H*');
    $this->actingAs($this->ahmad)->postJson('/files', ['file' => $eicar])->assertCreated();

    $file = KnowledgeFile::latest('id')->first();
    expect($file->status)->toBe('rejected')->and($file->rejection_reason)->toBe('malware')
        ->and(\Illuminate\Support\Facades\Storage::disk('local')->exists($file->path))->toBeFalse();
});

it('never lets a colleague read someone else\'s file', function () {
    $this->actingAs($this->ahmad)->post('/files', ['file' => docx('خاص')]);
    $file = KnowledgeFile::latest('id')->first();
    $colleague = \App\Models\User::where('email', 'admin@future.test')->first();

    $this->actingAs($colleague)->getJson("/files/{$file->id}")->assertNotFound();
});
