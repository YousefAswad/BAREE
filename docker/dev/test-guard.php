<?php
// Refuses to run the test suite if it could hit anything but the throw-away baree_test database.
$file = is_file('/app/phpunit.xml') ? '/app/phpunit.xml' : '/app/phpunit.xml.dist';
if (! is_file($file)) { fwrite(STDERR, "guard: no phpunit.xml found\n"); exit(2); }
$xml = simplexml_load_file($file);
$forced = [];
foreach ($xml->xpath('//php/env|//php/server') as $e) {
    if (preg_match('/^(DB_|DATABASE_URL)/', (string) $e['name']) && strtolower((string) $e['force']) === 'true') { $forced[] = (string) $e['name']; }
}
if ($forced) { fwrite(STDERR, 'guard: phpunit.xml forces '.implode(',', $forced)." - refusing to run\n"); exit(3); }
$db = getenv('DB_DATABASE');
if ($db !== 'baree_test') { fwrite(STDERR, "guard: DB_DATABASE must be baree_test, got '$db' - refusing to run\n"); exit(4); }
if (getenv('DB_HOST') !== 'pgsql') { fwrite(STDERR, "guard: DB_HOST must be the local compose service 'pgsql'\n"); exit(5); }
echo "guard: OK (database baree_test, phpunit.xml does not force DB settings)\n";
