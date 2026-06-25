<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Maximum Global Attempts
    |--------------------------------------------------------------------------
    */
    'max_attempts' => 5,

    /*
    |--------------------------------------------------------------------------
    | Driver Configurations
    |--------------------------------------------------------------------------
    */
    'drivers' => [
        'code' => [
            'safety_limits' => [
                'memory_mb' => 512,
                'cpu_percent' => 50,
                'max_output_mb' => 10,
                'network' => false,
                'timeout_seconds' => 60,
                'allow_file_write' => false,
            ],
            'timeout' => 60,
            'max_retries' => 2,
            'retryable_errors' => ['timeout', 'memory'],
        ],

        'llm' => [
            'timeout' => 120,
            'max_retries' => 3,
            'retryable_errors' => ['rate limit', 'timeout', '429', '503', '502', '504'],
        ],

        'shell' => [
            'safety_limits' => [
                'memory_mb' => 256,
                'cpu_percent' => 25,
                'max_output_mb' => 5,
                'network' => false,
                'timeout_seconds' => 30,
                'allow_file_write' => false,
            ],
            'timeout' => 30,
            'max_retries' => 1,
            'blocked_commands' => [
                'rm -rf /',
                'mkfs',
                'dd if=',
                'curl',
                'wget',
                'nc ',
                'netcat',
                'bash -i',
                'python -c .*socket',
                'ssh ',
                'scp ',
                'sudo ',
                'su -',
            ],
        ],

        'http' => [
            'timeout' => 30,
            'max_retries' => 3,
            'allowed_hosts' => [], // Empty = allow all (not recommended for production)
            'retryable_errors' => ['timeout', 'connection', '429', '503', '502', '504'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Sandbox Configuration
    |--------------------------------------------------------------------------
    */
    'sandbox' => [
        'docker_network' => 'none',
        'default_memory' => '512m',
        'default_cpus' => '0.5',

        'images' => [
            'php' => 'sandbox-php:8.3',
            'python' => 'sandbox-python:3.12',
            'javascript' => 'sandbox-node:20',
            'bash' => 'sandbox-alpine:latest',
            'ruby' => 'sandbox-ruby:3.3',
            'go' => 'sandbox-go:1.22',
            'rust' => 'sandbox-rust:1.78',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Retry Manager
    |--------------------------------------------------------------------------
    */
    'retry' => [
        'base_delay' => 2,
        'backoff_multiplier' => 2.0,
        'max_delay' => 300,
    ],
];