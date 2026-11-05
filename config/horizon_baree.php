<?php

// Merge into config/horizon.php → 'environments.production'. Each queue scales independently;
// AI and media are I/O-bound (waiting on providers), so they get more processes per CPU than CPU-bound work.
return [
    'production' => [
        'ai' => ['connection' => 'redis', 'queue' => ['ai'], 'balance' => 'auto', 'minProcesses' => 4, 'maxProcesses' => 60, 'tries' => 1, 'timeout' => 900],
        'media' => ['connection' => 'redis', 'queue' => ['media'], 'balance' => 'auto', 'minProcesses' => 2, 'maxProcesses' => 20, 'tries' => 1, 'timeout' => 330],
        'files' => ['connection' => 'redis', 'queue' => ['files', 'exports'], 'balance' => 'auto', 'minProcesses' => 2, 'maxProcesses' => 12, 'tries' => 3, 'timeout' => 300],
        'default' => ['connection' => 'redis', 'queue' => ['default'], 'balance' => 'auto', 'minProcesses' => 2, 'maxProcesses' => 20, 'tries' => 3, 'timeout' => 120],
    ],
];
