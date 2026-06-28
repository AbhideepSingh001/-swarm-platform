<?php

return [
    'executor' => [
        'queue' => 'executor',
        'max_retries' => 3,
        'retry_delay' => 5,
    ],

    'allowed_shell_commands' => [
        'git ',
        'php artisan ',
        'composer ',
        'npm ',
        'node ',
        'python3 ',
        'cat ',
        'ls ',
        'grep ',
    ],
    'result_store' => [
        'driver' => env('SWARM_RESULT_STORE_DRIVER', 'database'),
    ],
    'artifacts' => [
        'disk' => env('SWARM_ARTIFACTS_DISK', 'local'),
        'path' => env('SWARM_ARTIFACTS_PATH', 'artifacts'),
    ],

];