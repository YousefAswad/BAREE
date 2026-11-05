<?php

/*
 | Idempotently wires the BAREE overlay into a fresh Laravel starter-kit app.
 | Run from the app root:  php scripts/wire.php
 | Safe to run more than once; prints what it changed.
 */

function patch(string $file, callable $change): void
{
    if (! file_exists($file)) {
        fwrite(STDERR, "missing: {$file}\n");
        exit(1);
    }
    $before = file_get_contents($file);
    $after = $change($before);
    if ($after !== $before) {
        file_put_contents($file, $after);
        echo "patched: {$file}\n";
    }
}

patch('bootstrap/providers.php', fn ($s) => str_contains($s, 'BareeServiceProvider') ? $s
    : preg_replace('/return \[\n/', "return [\n    App\\Providers\\BareeServiceProvider::class,\n", $s, 1));

patch('routes/web.php', function ($s) {
    foreach (['baree', 'platform', 'institution', 'studio', 'learning', 'operations'] as $file) {
        $line = "require __DIR__.'/{$file}.php';";
        if (! str_contains($s, $line)) {
            $s = rtrim($s)."\n{$line}\n";
        }
    }

    return $s;
});

patch('routes/console.php', fn ($s) => str_contains($s, "/schedule.php'") ? $s : rtrim($s)."\nrequire __DIR__.'/schedule.php';\n");

patch('config/services.php', fn ($s) => str_contains($s, "'gotenberg'") ? $s
    : preg_replace('/\n\];\s*$/', "\n\n    'gotenberg' => ['url' => env('GOTENBERG_URL', 'http://127.0.0.1:3000')],\n];\n", $s, 1));

patch('app/Http/Middleware/HandleInertiaRequests.php', fn ($s) => str_contains($s, "'flash'") ? $s
    : preg_replace('/(\.\.\.parent::share\(\$request\),\n)/', "$1            'flash' => ['status' => fn () => \$request->session()->get('status')],\n", $s, 1));

patch('resources/js/app.ts', function ($s) {
    if (str_contains($s, 'createBareeI18n')) {
        return $s;
    }
    $s = preg_replace('/(import [^\n]+;\n)(?!import)/', "$1import { createBareeI18n } from './i18n';\n", $s, 1);

    return preg_replace('/\.use\(plugin\)/', '.use(plugin).use(createBareeI18n(props.initialPage.props.locale as string))', $s, 1);
});

echo "wiring done\n";
