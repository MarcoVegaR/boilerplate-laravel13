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

    'planning' => [
        'thresholds' => [
            'deterministic' => 100,
            'extended' => 90,
        ],
        'matrices' => [
            'deterministic' => [
                ['prompt' => 'cuantos usuarios hay', 'intent_family' => 'read_metrics', 'capability_key' => 'users.metrics.total'],
                ['prompt' => 'cuantos usuarios activos hay', 'intent_family' => 'read_metrics', 'capability_key' => 'users.metrics.active'],
                ['prompt' => 'cuantos usuarios inactivos hay', 'intent_family' => 'read_metrics', 'capability_key' => 'users.metrics.inactive'],
                ['prompt' => 'cuantos usuarios tienen roles', 'intent_family' => 'read_metrics', 'capability_key' => 'users.metrics.with_roles'],
                ['prompt' => 'cuantos usuarios no tienen roles', 'intent_family' => 'read_metrics', 'capability_key' => 'users.metrics.without_roles'],
                ['prompt' => 'cuantos usuarios estan verificados', 'intent_family' => 'read_metrics', 'capability_key' => 'users.metrics.verified'],
                ['prompt' => 'cuantos usuarios no estan verificados', 'intent_family' => 'read_metrics', 'capability_key' => 'users.metrics.unverified'],
                ['prompt' => 'cual es la distribucion de roles', 'intent_family' => 'read_metrics', 'capability_key' => 'users.metrics.role_distribution'],
                ['prompt' => 'cual es el rol mas comun', 'intent_family' => 'read_metrics', 'capability_key' => 'users.metrics.most_common_role'],
            ],
            'extended' => [
                ['prompt' => 'dame el total actual de usuarios del sistema', 'intent_family' => 'read_metrics', 'capability_key' => 'users.metrics.total'],
                ['prompt' => 'de esos cuantos siguen activos', 'intent_family' => 'read_metrics', 'capability_key' => 'users.metrics.active', 'requires_snapshot' => true],
                ['prompt' => 'y cuantos son', 'intent_family' => 'read_metrics', 'capability_key' => null, 'requires_snapshot' => true],
                ['prompt' => 'ahora solo los inactivos', 'intent_family' => 'read_search', 'capability_key' => 'users.search', 'requires_snapshot' => true],
                ['prompt' => 'muestrame los no verificados', 'intent_family' => 'read_search', 'capability_key' => 'users.search'],
                ['prompt' => 'si digo mario cual usuario es', 'intent_family' => 'ambiguous', 'capability_key' => 'users.clarification'],
            ],
        ],
    ],

    'fallback' => [
        'message' => 'No pude procesar la solicitud del copiloto de usuarios de forma segura. Intenta nuevamente con una instruccion mas concreta.',
    ],
];
