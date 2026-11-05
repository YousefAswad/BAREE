<?php

namespace App\Services\Institution;

use App\Jobs\ImportUsersJob;
use App\Models\Department;
use App\Models\JobTitle;
use App\Models\User;
use App\Models\UserImport;
use App\Support\Audit;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Two-step bulk invite: (1) stage = parse + validate every row and show a report,
 * (2) commit = invite only the valid rows in a queued job.
 * Expected columns (Arabic or English headers): name, email, department, job_title, role
 */
final class UserImportService
{
    public const MAX_ROWS = 5000;

    private const HEADERS = [
        'name' => ['name', 'الاسم'], 'email' => ['email', 'البريد', 'البريد الإلكتروني'],
        'department' => ['department', 'القسم'], 'job_title' => ['job_title', 'المسمى', 'المسمّى الوظيفي'], 'role' => ['role', 'الدور'],
    ];

    public function stage(UploadedFile $file, User $actor): UserImport
    {
        $path = $file->storeAs('imports/'.$actor->tenant_id, Str::ulid().'.csv', 'local');
        $rows = $this->parse(Storage::disk('local')->get($path));

        if (count($rows) > self::MAX_ROWS) {
            throw new DomainException(__('Maximum :n rows per file.', ['n' => self::MAX_ROWS]));
        }

        $departments = Department::query()->pluck('id', 'name');
        $titles = JobTitle::query()->pluck('id', 'name');
        $existing = User::query()->whereIn('email', array_column($rows, 'email'))->pluck('email')->flip();
        $seen = [];
        $report = [];
        $valid = 0;

        foreach ($rows as $i => $row) {
            $problems = [];
            $email = Str::lower(trim($row['email'] ?? ''));

            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $problems[] = 'invalid_email';
            } elseif (isset($seen[$email])) {
                $problems[] = 'duplicate_in_file';
            } elseif (isset($existing[$email])) {
                $problems[] = 'already_a_user';
            }
            if (filled($row['department'] ?? null) && ! isset($departments[$row['department']])) {
                $problems[] = 'unknown_department';
            }
            if (filled($row['job_title'] ?? null) && ! isset($titles[$row['job_title']])) {
                $problems[] = 'unknown_job_title';
            }

            $seen[$email] = true;
            $valid += (int) ($problems === []);
            $report[] = [
                'row' => $i + 2, 'name' => $row['name'] ?? null, 'email' => $email, 'problems' => $problems,
                'department_id' => $departments[$row['department'] ?? ''] ?? null,
                'job_title_id' => $titles[$row['job_title'] ?? ''] ?? null,
                'role_key' => $row['role'] ?: 'staff',
            ];
        }

        $import = UserImport::create([
            'uploaded_by' => $actor->id, 'path' => $path, 'status' => 'ready',
            'total' => count($rows), 'valid' => $valid, 'report' => $report,
        ]);
        Audit::log('users.import_staged', $import, ['total' => count($rows), 'valid' => $valid]);

        return $import;
    }

    public function commit(UserImport $import, User $actor): void
    {
        if ($import->status !== 'ready') {
            throw new DomainException(__('This import was already processed.'));
        }

        $import->update(['status' => 'importing']);
        ImportUsersJob::dispatch($import->id, $actor->id);
    }

    /** @return array<int, array<string, string>> */
    public function parse(string $contents): array
    {
        $contents = preg_replace('/^\xEF\xBB\xBF/', '', $contents);
        $lines = array_values(array_filter(preg_split('/\r\n|\r|\n/', $contents), fn ($l) => trim($l) !== ''));
        if ($lines === []) {
            return [];
        }

        $header = array_map(fn ($h) => trim(Str::lower($h)), str_getcsv(array_shift($lines)));
        $map = [];
        foreach (self::HEADERS as $key => $aliases) {
            foreach ($header as $index => $column) {
                if (in_array($column, $aliases, true)) {
                    $map[$key] = $index;
                }
            }
        }
        if (! isset($map['email'])) {
            throw new DomainException(__('The file needs an e-mail column.'));
        }

        return array_map(function ($line) use ($map) {
            $cells = str_getcsv($line);
            // Neutralise spreadsheet formula injection in anything we might export again later.
            $clean = fn ($v) => ltrim(trim((string) $v), "=+-@\t\r");

            return array_map(fn ($index) => $clean($cells[$index] ?? ''), $map) + ['role' => ''];
        }, $lines);
    }
}
