<?php

return [
    'llm' => [
        'driver' => env('LLM_DRIVER', 'gemini'),

        'gemini' => [
            'api_keys' => [
                env('GEMINI_API_KEY_1'),
                env('GEMINI_API_KEY_2'),
                env('GEMINI_API_KEY_3'),
            ],
            'key_tiers' => [
                env('GEMINI_KEY_TIER_1', 'pro'),
                env('GEMINI_KEY_TIER_2', 'free'),
                env('GEMINI_KEY_TIER_3', 'free'),
            ],
            'model' => env('GEMINI_MODEL', 'gemini-3.5-flash'),
            'base_url' => 'https://generativelanguage.googleapis.com/v1beta/models',
            'timeout' => 60,
            'max_retries' => 3,
            'retry_delay' => 2,
            'rate_limit_cooldown' => 60,
        ],

        'ollama' => [
            'base_url' => env('OLLAMA_URL', 'http://localhost:11434'),
            'model' => env('OLLAMA_MODEL', 'llama3.1:8b'),
            'timeout' => 120,
        ],

        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
            'base_url' => 'https://api.openai.com/v1',
        ],

        'anthropic' => [
            'api_key' => env('ANTHROPIC_API_KEY'),
            'model' => env('ANTHROPIC_MODEL', 'claude-3-haiku-20240307'),
            'base_url' => 'https://api.anthropic.com/v1',
        ],
    ],

    'planner' => [
        'cache_ttl' => 3600,
        'max_tasks_per_plan' => 50,
        'default_priority' => 'medium',
        'allowed_priorities' => ['low', 'medium', 'high', 'critical'],
    ],

    'executor' => [
        'max_concurrent_tasks' => 5,
        'task_timeout' => 300,
        'retry_failed_tasks' => true,
        'max_task_retries' => 2,
    ],
];