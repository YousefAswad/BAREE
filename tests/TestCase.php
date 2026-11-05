<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use App\Support\Tenancy\TenantContext;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    /**
     * SetTenantContext::terminate() clears the tenant after every request (on purpose: no leakage between requests on long-lived workers).
     * In a test the "ambient" tenant the test set up must survive its own HTTP calls, otherwise assertions that query tenant-owned models
     * after a request would silently see nothing (fail-closed scope). Production behaviour is untouched.
     */
    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        $context = $this->app->make(TenantContext::class);
        $tenantId = $context->id();

        try {
            return parent::call($method, $uri, $parameters, $cookies, $files, $server, $content);
        } finally {
            try {
                if ($tenantId !== null) {
                    $context->set($tenantId);
                }
            } catch (\Throwable) {
                // a failed request may have left the DB transaction aborted; the original error is what matters
            }
        }
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
