<?php

namespace App\Services\Files;

use App\Jobs\ProcessUploadedFileJob;
use App\Models\KnowledgeFile;
use App\Models\User;
use App\Support\Audit;
use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * Every upload: real MIME sniffed from bytes (never trust the extension), size limit, random storage name
 * under the tenant prefix, SHA-256, then status "pending" until scanned and extracted.
 * Agents only ever read files in status "indexed".
 */
final class FileIntakeService
{
    public const ALLOWED = [
        'application/pdf' => 'pdf',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
        'text/plain' => 'txt',
        'text/csv' => 'csv',
        'text/markdown' => 'md',
    ];

    public function store(UploadedFile $file, User $user, Model $owner, string $usage = 'on_demand'): KnowledgeFile
    {
        $maxBytes = (int) config('baree.uploads.max_mb', 50) * 1024 * 1024;
        if ($file->getSize() > $maxBytes) {
            throw new DomainException(__('File is larger than :n MB.', ['n' => config('baree.uploads.max_mb')]));
        }

        $mime = $this->sniff($file);
        if (! isset(self::ALLOWED[$mime])) {
            throw new DomainException(__('This file type is not supported.'));
        }

        $disk = config('filesystems.default');
        $path = $file->storeAs('tenants/'.$user->tenant_id.'/files/'.now()->format('Y/m'), Str::ulid().'.'.self::ALLOWED[$mime], $disk);

        $record = KnowledgeFile::create([
            'owner_type' => $owner->getMorphClass(),
            'owner_id' => $owner->getKey(),
            'disk' => $disk,
            'path' => $path,
            'original_name' => Str::limit(basename($file->getClientOriginalName()), 180, ''),
            'mime' => $mime,
            'size' => $file->getSize(),
            'sha256' => hash_file('sha256', $file->getRealPath()),
            'usage_mode' => in_array($usage, ['always', 'on_demand', 'off'], true) ? $usage : 'on_demand',
            'status' => 'pending',
            'uploaded_by' => $user->id,
        ]);

        ProcessUploadedFileJob::dispatch($record->id, $user->tenant_id);
        Audit::log('file.uploaded', $record, ['mime' => $mime, 'size' => $file->getSize()]);

        return $record;
    }

    private function sniff(UploadedFile $file): string
    {
        $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($file->getRealPath());

        // Office files are ZIP containers: confirm by their internal structure, then map by extension.
        if (in_array($mime, ['application/zip', 'application/octet-stream'], true) || str_starts_with($mime, 'application/vnd.openxmlformats')) {
            $zip = new \ZipArchive;
            if ($zip->open($file->getRealPath()) === true) {
                $kind = match (true) {
                    $zip->locateName('word/document.xml') !== false => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    $zip->locateName('ppt/presentation.xml') !== false => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                    $zip->locateName('xl/workbook.xml') !== false => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    default => $mime,
                };
                $hasMacros = $zip->locateName('word/vbaProject.bin') !== false || $zip->locateName('xl/vbaProject.bin') !== false || $zip->locateName('ppt/vbaProject.bin') !== false;
                $zip->close();
                if ($hasMacros) {
                    throw new DomainException(__('Files with macros are not accepted.'));
                }

                return $kind;
            }
        }

        if ($mime === 'text/plain' && in_array(strtolower($file->getClientOriginalExtension()), ['csv', 'md'], true)) {
            return $file->getClientOriginalExtension() === 'csv' ? 'text/csv' : 'text/markdown';
        }

        return $mime;
    }
}
