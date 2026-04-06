<?php

use App\Ai\Support\CopilotConversationSnapshot;
use App\Ai\Support\CopilotStructuredOutput;
use Tests\TestCase;

uses(TestCase::class);

it('keeps clarification payloads authoritative over contradictory actions', function () {
    $payload = CopilotStructuredOutput::reconstruct(
        payload: [
            'answer' => 'Voy a desactivar al usuario ahora mismo.',
            'intent' => 'action_proposal',
            'cards' => [[
                'kind' => 'clarification',
                'title' => 'Necesito aclaracion',
                'summary' => 'Hay varios usuarios posibles.',
                'data' => [
                    'reason' => 'ambiguous_target',
                    'question' => 'Cual Mario quieres revisar?',
                    'options' => [['label' => 'Mario Vega', 'value' => 'user:10']],
                ],
            ]],
            'actions' => [[
                'kind' => 'action_proposal',
                'action_type' => 'deactivate',
                'target' => ['kind' => 'user', 'user_id' => 10],
                'summary' => 'Desactiva a Mario.',
                'payload' => ['reason' => 'copilot_confirmed_action'],
                'can_execute' => true,
                'deny_reason' => null,
                'required_permissions' => ['system.users.deactivate'],
            ]],
            'requires_confirmation' => true,
            'references' => [['label' => 'Usuarios', 'href' => '/system/users']],
            'meta' => [
                'module' => 'users',
                'channel' => 'web',
                'subject_user_id' => null,
                'fallback' => false,
                'diagnostics' => null,
            ],
        ],
        plan: [
            'intent_family' => 'ambiguous',
            'capability_key' => 'users.clarification',
        ],
        snapshot: CopilotConversationSnapshot::empty(),
    );

    expect($payload)
        ->toMatchArray([
            'intent' => 'ambiguous',
            'requires_confirmation' => false,
        ])
        ->and($payload['answer'])->toBe('Cual Mario quieres revisar?')
        ->and($payload['actions'])->toBe([])
        ->and(data_get($payload, 'meta.capability_key'))->toBe('users.clarification');
});

it('strips action confirmations from non action plans', function () {
    $payload = CopilotStructuredOutput::reconstruct(
        payload: [
            'answer' => 'Hay 5 usuarios activos.',
            'intent' => 'metrics',
            'cards' => [],
            'actions' => [[
                'kind' => 'action_proposal',
                'action_type' => 'deactivate',
                'target' => ['kind' => 'user', 'user_id' => 10],
                'summary' => 'No deberia salir.',
                'payload' => ['reason' => 'copilot_confirmed_action'],
                'can_execute' => true,
                'deny_reason' => null,
                'required_permissions' => ['system.users.deactivate'],
            ]],
            'requires_confirmation' => true,
            'references' => [],
            'meta' => [
                'module' => 'users',
                'channel' => 'web',
                'subject_user_id' => null,
                'fallback' => false,
                'diagnostics' => null,
            ],
        ],
        plan: [
            'intent_family' => 'read_metrics',
            'capability_key' => 'users.metrics.active',
        ],
        snapshot: CopilotConversationSnapshot::empty(),
    );

    expect($payload['actions'])->toBe([])
        ->and($payload['requires_confirmation'])->toBeFalse();
});

it('rebuilds snapshot subset count responses from conversation state', function () {
    $payload = CopilotStructuredOutput::reconstruct(
        payload: [
            'answer' => 'Respuesta incorrecta.',
            'intent' => 'help',
            'cards' => [],
            'actions' => [],
            'requires_confirmation' => false,
            'references' => [],
            'meta' => [
                'module' => 'users',
                'channel' => 'web',
                'subject_user_id' => null,
                'fallback' => false,
                'diagnostics' => ['reason' => 'provider_guess'],
            ],
        ],
        plan: [
            'intent_family' => 'read_metrics',
            'capability_key' => 'users.snapshot.result_count',
        ],
        snapshot: new CopilotConversationSnapshot([
            'last_result_count' => 4,
            'last_filters' => ['status' => 'inactive'],
        ]),
    );

    expect($payload)
        ->toMatchArray([
            'intent' => 'metrics',
            'requires_confirmation' => false,
        ])
        ->and($payload['answer'])->toBe('El subconjunto actual tiene 4 usuarios.')
        ->and(data_get($payload, 'cards.0.data.metric.value'))->toBe(4)
        ->and(data_get($payload, 'meta.diagnostics.source_of_truth'))->toBe('conversation_snapshot');
});
