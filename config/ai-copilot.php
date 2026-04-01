<?php

$providerList = array_values(array_filter(array_map(
    static fn (string $provider): string => trim($provider),
    explode(',', (string) env('AI_COPILOT_PROVIDERS', '')),
)));

$defaultProvider = count($providerList) > 0
    ? $providerList
    : env('AI_COPILOT_PROVIDER', config('ai.default'));

return [
    'enabled' => (bool) env('AI_COPILOT_ENABLED', false),

    'modules' => [
        'users' => [
            'enabled' => (bool) env('AI_COPILOT_USERS_ENABLED', true),
        ],
    ],

    'channels' => [
        'web' => [
            'enabled' => (bool) env('AI_COPILOT_WEB_ENABLED', true),
        ],
    ],

    'providers' => [
        'default' => $defaultProvider,
    ],

    'model' => env('AI_COPILOT_MODEL'),

    'limits' => [
        'timeout' => (int) env('AI_COPILOT_TIMEOUT', 30),
        'temperature' => (float) env('AI_COPILOT_TEMPERATURE', 0.2),
        'step_limit' => (int) env('AI_COPILOT_STEP_LIMIT', 4),
        'context_window' => (int) env('AI_COPILOT_CONTEXT_WINDOW', 20),
        'prompt_length' => (int) env('AI_COPILOT_PROMPT_LENGTH', 4000),
    ],

    'rate_limits' => [
        'messages_per_minute' => (int) env('AI_COPILOT_MESSAGES_PER_MINUTE', 6),
    ],

    'observability' => [
        'enabled' => (bool) env('AI_COPILOT_OBSERVABILITY_ENABLED', true),
        'debug' => (bool) env('AI_COPILOT_OBSERVABILITY_DEBUG', false),
    ],

    'confirmation' => [
        'required' => (bool) env('AI_COPILOT_CONFIRMATION_REQUIRED', true),
        'ttl_minutes' => (int) env('AI_COPILOT_CONFIRMATION_TTL_MINUTES', 15),
    ],

    'fallback' => [
        'message' => 'No pude procesar la solicitud del copiloto de usuarios de forma segura. Intenta nuevamente con una instruccion mas concreta.',
    ],
];
