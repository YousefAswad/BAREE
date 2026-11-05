<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Throwable;

/** Deep health for monitoring (token protected). Laravel's /up stays the cheap liveness probe. */
final class HealthController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        abort_unless(hash_equals((string) config('baree.health_token'), (string) $request->bearerToken()) && config('baree.health_token'), 404);

        $checks = [
            'database' => fn () => DB::select('select 1') && true,
            'cache' => fn () => Cache::put('health', 1, 10) && Cache::get('health') === 1,
            'storage' => fn () => Storage::put('health/ping', now()->toIso8601String()) && Storage::delete('health/ping'),
            'queue_ai_backlog' => fn () => Queue::size('ai') < 500,
            'queue_default_backlog' => fn () => Queue::size('default') < 2000,
            'clamav' => function () {
                if (config('baree.uploads.scan_with') !== 'clamav') {
                    return true;
                }
                $socket = @fsockopen(config('baree.uploads.clamav_host'), config('baree.uploads.clamav_port'), $e, $s, 2);
                if (! $socket) {
                    return false;
                }
                fwrite($socket, "zPING\0");
                $ok = str_contains((string) fread($socket, 16), 'PONG');
                fclose($socket);

                return $ok;
            },
        ];

        $results = [];
        foreach ($checks as $name => $check) {
            try {
                $results[$name] = (bool) $check();
            } catch (Throwable) {
                $results[$name] = false;
            }
        }
        $healthy = ! in_array(false, $results, true);

        return response()->json(['healthy' => $healthy, 'checks' => $results, 'version' => config('baree.version')], $healthy ? 200 : 503);
    }
}
