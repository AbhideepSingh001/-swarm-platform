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
];