<?php

namespace App\Services\Learning;

use App\Models\Certificate;
use App\Models\Course;
use App\Models\User;
use App\Support\Audit;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Str;

/** Verifiable certificates: anyone can check a serial; any change to the record breaks the signature. */
final class CertificateService
{
    public function __construct(private TenantContext $context) {}

    public function issue(User $user, Course $course): Certificate
    {
        $existing = Certificate::query()->where('user_id', $user->id)->where('course_id', $course->id)->whereNull('revoked_at')->first();
        if ($existing) {
            return $existing;
        }

        $data = ['serial' => (string) Str::ulid(), 'user_id' => $user->id, 'course_id' => $course->id, 'holder_name' => $user->name,
            'hours' => $course->hours, 'issued_at' => now()->startOfSecond()];
        $certificate = Certificate::create($data + ['signature' => $this->sign($data)]);
        Audit::log('certificate.issued', $certificate, ['course' => $course->key]);

        return $certificate;
    }

    /** Public verification by serial. Returns only what is printed on the certificate. */
    public function verify(string $serial): ?array
    {
        $certificate = $this->context->asPlatform(fn () => Certificate::query()->where('serial', $serial)->first());
        if (! $certificate) {
            return null;
        }

        $valid = $certificate->revoked_at === null && hash_equals($certificate->signature, $this->sign([
            'serial' => $certificate->serial, 'user_id' => $certificate->user_id, 'course_id' => $certificate->course_id,
            'holder_name' => $certificate->holder_name, 'hours' => $certificate->hours, 'issued_at' => $certificate->issued_at,
        ]));
        $course = $this->context->asPlatform(fn () => Course::query()->find($certificate->course_id));

        return ['valid' => $valid, 'holder' => $certificate->holder_name, 'course' => $course?->title, 'hours' => $certificate->hours,
            'issued_at' => $certificate->issued_at->toDateString(), 'revoked' => $certificate->revoked_at !== null];
    }

    private function sign(array $d): string
    {
        $payload = implode('|', [$d['serial'], $d['user_id'], $d['course_id'], $d['holder_name'], number_format((float) $d['hours'], 2, '.', ''), $d['issued_at']->format('c')]);

        return hash_hmac('sha256', $payload, (string) config('app.key'));
    }
}
