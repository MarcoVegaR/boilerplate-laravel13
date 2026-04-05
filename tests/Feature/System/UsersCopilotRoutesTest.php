<?php

use App\Ai\Agents\System\UsersCopilotAgent;
use App\Ai\Agents\System\UsersGeminiCopilotAgent;
use App\Ai\Testing\BrowserCopilotFakeTransport;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\AccessModulePermissionsSeeder;
use Database\Seeders\AiCopilotPermissionsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Prompts\AgentPrompt;
use Tests\Support\CopilotBrowserFake;

beforeEach(function () {
    CopilotBrowserFake::clear();

    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(AccessModulePermissionsSeeder::class);
    $this->seed(AiCopilotPermissionsSeeder::class);

    config(['ai-copilot.enabled' => true]);
    config()->set('ai-copilot.providers.default', 'openai');
    config()->set('ai-copilot.model', 'gpt-5.4');
});

afterEach(function () {
    CopilotBrowserFake::clear();
});

function authorizedCopilotOperator(): User
{
    $role = Role::factory()->active()->create();
    $role->syncPermissions(['system.users.view', 'system.users-copilot.view']);

    $user = User::factory()->withTwoFactor()->create();
    $user->assignRole($role);

    return $user;
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function fakeCopilotResponse(array $overrides = []): array
{
    return array_replace_recursive([
        'answer' => 'Encontré usuarios inactivos para revisar.',
        'intent' => 'search_results',
        'cards' => [
            [
                'kind' => 'search_results',
                'title' => 'Usuarios inactivos',
                'summary' => 'Hay 1 usuario inactivo.',
                'data' => [
                    'count' => 1,
                    'users' => [],
                ],
            ],
        ],
        'actions' => [],
        'requires_confirmation' => false,
        'references' => [],
        'meta' => [
            'module' => 'users',
            'channel' => 'web',
            'subject_user_id' => null,
            'fallback' => false,
            'diagnostics' => null,
        ],
    ], $overrides);
}

it('registers the copilot message route with throttle middleware', function () {
    $route = app('router')->getRoutes()->getByName('system.users.copilot.messages');

    expect($route)->not->toBeNull()
        ->and($route->uri())->toBe('system/users/copilot/messages')
        ->and($route->methods())->toContain('POST')
        ->and($route->gatherMiddleware())->toContain('throttle:users-copilot-messages');
});

it('registers the copilot action route', function () {
    $route = app('router')->getRoutes()->getByName('system.users.copilot.actions');

    expect($route)->not->toBeNull()
        ->and($route->uri())->toBe('system/users/copilot/actions/{actionType}')
        ->and($route->methods())->toContain('POST');
});

it('returns 403 for a user without copilot view permission on the message endpoint', function () {
    $user = User::factory()->withTwoFactor()->create();

    $this->actingAs($user)
        ->postJson(route('system.users.copilot.messages'), [
            'prompt' => 'Necesito ayuda con usuarios',
        ])
        ->assertForbidden();
});

it('returns validation errors for oversized prompts before controller execution', function () {
    config(['ai-copilot.limits.prompt_length' => 20]);

    $user = authorizedCopilotOperator();

    $this->actingAs($user)
        ->postJson(route('system.users.copilot.messages'), [
            'prompt' => str_repeat('a', 21),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['prompt']);
});

it('returns a real read-only message envelope and persists the conversation', function () {
    $user = authorizedCopilotOperator();

    UsersCopilotAgent::fake([
        fakeCopilotResponse(),
    ])->preventStrayPrompts();

    $response = $this->actingAs($user)
        ->postJson(route('system.users.copilot.messages'), [
            'prompt' => 'Busca usuarios inactivos.',
        ]);

    $response->assertSuccessful()
        ->assertJsonPath('response.intent', 'search_results')
        ->assertJsonPath('response.requires_confirmation', false)
        ->assertJsonPath('response.meta.fallback', false);

    $conversationId = $response->json('conversation_id');

    expect($conversationId)->not->toBeEmpty()
        ->and(DB::table('agent_conversations')->where('id', $conversationId)->value('user_id'))->toBe($user->id)
        ->and(DB::table('agent_conversations')->where('id', $conversationId)->value('title'))->toBe('Busca usuarios inactivos.')
        ->and(DB::table('agent_conversation_messages')->where('conversation_id', $conversationId)->count())->toBe(2);

    UsersCopilotAgent::assertPrompted(function (AgentPrompt $prompt): bool {
        return $prompt->contains('Busca usuarios inactivos.');
    });
});

it('uses the browser fake transport for HTTP copilot requests regardless of provider', function () {
    config()->set('ai-copilot.providers.default', 'gemini');
    config()->set('ai-copilot.model', 'gemini-2.5-flash-lite');

    $user = authorizedCopilotOperator();

    CopilotBrowserFake::write([
        fakeCopilotResponse([
            'answer' => 'Respuesta determinística desde el transporte fake del navegador.',
            'actions' => [
                [
                    'kind' => 'action_proposal',
                    'action_type' => 'send_reset',
                    'target' => [
                        'kind' => 'user',
                        'user_id' => 77,
                        'name' => 'Provider Agnostic',
                        'email' => 'provider-agnostic@example.test',
                        'is_active' => true,
                    ],
                    'summary' => 'Envía un restablecimiento de contraseña.',
                    'payload' => [
                        'reason' => 'copilot_confirmed_action',
                    ],
                    'can_execute' => true,
                    'deny_reason' => null,
                    'required_permissions' => ['system.users.send-reset', 'system.users-copilot.execute'],
                ],
            ],
            'requires_confirmation' => true,
        ]),
    ]);

    $response = $this->actingAs($user)
        ->postJson(route('system.users.copilot.messages'), [
            'prompt' => 'Necesito una propuesta determinística.',
        ]);

    $response->assertSuccessful()
        ->assertJsonPath('response.answer', 'Respuesta determinística desde el transporte fake del navegador.')
        ->assertJsonPath('response.actions.0.target.email', 'provider-agnostic@example.test')
        ->assertJsonPath('response.meta.fallback', false);

    $conversationId = $response->json('conversation_id');
    $assistantMessage = DB::table('agent_conversation_messages')
        ->where('conversation_id', $conversationId)
        ->where('role', 'assistant')
        ->first();

    expect($assistantMessage)->not->toBeNull()
        ->and(json_decode($assistantMessage->meta, true, 512, JSON_THROW_ON_ERROR))->toMatchArray([
            'payload_source' => 'browser_file',
        ])
        ->and(File::exists(BrowserCopilotFakeTransport::path()))->toBeFalse();
});

it('uses local gemini orchestration for inactive-user queries even when formatter output is invalid', function () {
    config()->set('ai-copilot.providers.default', 'gemini');
    config()->set('ai-copilot.model', 'gemini-2.5-flash-lite');

    $user = authorizedCopilotOperator();

    User::factory()->inactive()->unverified()->create([
        'name' => 'Irene Inactiva',
        'email' => 'irene@example.com',
    ]);

    UsersGeminiCopilotAgent::fake(['Respuesta plana']);

    $response = $this->actingAs($user)
        ->postJson(route('system.users.copilot.messages'), [
            'prompt' => 'Busca usuarios inactivos y resume su estado actual',
        ])
        ->assertSuccessful()
        ->assertJsonPath('response.intent', 'search_results')
        ->assertJsonPath('response.meta.fallback', false)
        ->assertJsonPath('response.meta.module', 'users')
        ->assertJsonPath('response.cards.0.kind', 'search_results')
        ->assertJsonPath('response.cards.0.data.count', 1)
        ->assertJsonPath('response.cards.0.data.users.0.email', 'irene@example.com');

    expect($response->json('response.meta.diagnostics.execution'))->toBe('local_capability_orchestrator')
        ->and($response->json('response.meta.diagnostics.capability'))->toBe('inactive_search')
        ->and($response->json('response.meta.diagnostics.formatter_reason'))->toBe('missing_structured_output');
});

it('merges gemini formatter text into the deterministic action proposal payload', function () {
    config()->set('ai-copilot.providers.default', 'gemini');
    config()->set('ai-copilot.model', 'gemini-2.5-flash-lite');

    $user = authorizedCopilotOperator();
    $target = User::factory()->active()->create([
        'name' => 'Gemini Target',
        'email' => 'gemini@example.com',
    ]);

    UsersGeminiCopilotAgent::fake([
        [
            'answer' => 'Preparé una propuesta compatible con Gemini.',
            'intent' => 'action_proposal',
            'cards' => [
                [
                    'kind' => 'notice',
                    'title' => 'Compatibilidad',
                    'summary' => 'Salida segura para Gemini.',
                    'data_json' => json_encode(['provider' => 'gemini'], JSON_THROW_ON_ERROR),
                ],
            ],
            'actions' => [
                [
                    'kind' => 'action_proposal',
                    'action_type' => 'send_reset',
                    'target_json' => json_encode([
                        'kind' => 'user',
                        'user_id' => 44,
                        'name' => 'Gemini Target',
                        'email' => 'gemini@example.com',
                        'is_active' => true,
                    ], JSON_THROW_ON_ERROR),
                    'summary' => 'Envía un restablecimiento al usuario.',
                    'payload_json' => json_encode([
                        'reason' => 'copilot_confirmed_action',
                    ], JSON_THROW_ON_ERROR),
                    'can_execute' => true,
                    'deny_reason' => null,
                    'required_permissions' => ['system.users.send-reset', 'system.users-copilot.execute'],
                ],
            ],
            'requires_confirmation' => true,
            'references' => [],
            'meta' => [
                'module' => 'users',
                'channel' => 'web',
                'subject_user_id' => $target->id,
                'fallback' => false,
                'diagnostics_json' => json_encode(['profile' => 'gemini'], JSON_THROW_ON_ERROR),
            ],
        ],
    ])->preventStrayPrompts();

    $this->actingAs($user)
        ->postJson(route('system.users.copilot.messages'), [
            'prompt' => 'Propón enviar un restablecimiento a este usuario.',
            'subject_user_id' => $target->id,
        ])
        ->assertSuccessful()
        ->assertJsonPath('response.actions.0.target.email', 'gemini@example.com')
        ->assertJsonPath('response.actions.0.payload.reason', 'copilot_confirmed_action')
        ->assertJsonPath('response.actions.0.summary', 'Envía un restablecimiento al usuario.')
        ->assertJsonPath('response.answer', 'Preparé una propuesta compatible con Gemini.')
        ->assertJsonPath('response.meta.diagnostics.formatter_result', 'gemini_text_json')
        ->assertJsonPath('response.meta.fallback', false);
});

it('prioritizes an explicitly mentioned user over the inactive keyword heuristic', function () {
    config()->set('ai-copilot.providers.default', 'gemini');
    config()->set('ai-copilot.model', 'gemini-2.5-flash-lite');

    $user = authorizedCopilotOperator();
    $target = User::factory()->active()->create([
        'name' => 'Inactive Copilot Check',
        'email' => 'inactive-copilot-check@example.com',
    ]);

    UsersGeminiCopilotAgent::fake(['Respuesta plana'])->preventStrayPrompts();

    $this->actingAs($user)
        ->postJson(route('system.users.copilot.messages'), [
            'prompt' => 'Explícame el acceso efectivo de Inactive Copilot Check',
        ])
        ->assertSuccessful()
        ->assertJsonPath('response.intent', 'user_context')
        ->assertJsonPath('response.cards.0.kind', 'user_context')
        ->assertJsonPath('response.cards.0.data.user.email', $target->email)
        ->assertJsonPath('response.meta.fallback', false);
});

it('can prepare a reset proposal for a user mentioned by name without explicit subject context', function () {
    config()->set('ai-copilot.providers.default', 'gemini');
    config()->set('ai-copilot.model', 'gemini-2.5-flash-lite');

    $user = authorizedCopilotOperator();
    $target = User::factory()->active()->create([
        'name' => 'Resettable User',
        'email' => 'resettable@example.test',
    ]);

    UsersGeminiCopilotAgent::fake(['Respuesta plana'])->preventStrayPrompts();

    $this->actingAs($user)
        ->postJson(route('system.users.copilot.messages'), [
            'prompt' => 'Necesito enviar un restablecimiento a Resettable User',
        ])
        ->assertSuccessful()
        ->assertJsonPath('response.intent', 'action_proposal')
        ->assertJsonPath('response.actions.0.action_type', 'send_reset')
        ->assertJsonPath('response.actions.0.target.user_id', $target->id)
        ->assertJsonPath('response.actions.0.target.email', $target->email)
        ->assertJsonPath('response.meta.fallback', false);
});

it('keeps the deterministic action proposal when gemini returns invalid json fragments', function () {
    config()->set('ai-copilot.providers.default', 'gemini');

    $user = authorizedCopilotOperator();
    $target = User::factory()->active()->create([
        'name' => 'Invalid Gemini Target',
        'email' => 'invalid-gemini@example.com',
    ]);

    UsersGeminiCopilotAgent::fake([
        [
            'answer' => 'Respuesta inválida.',
            'intent' => 'action_proposal',
            'cards' => [],
            'actions' => [
                [
                    'kind' => 'action_proposal',
                    'action_type' => 'send_reset',
                    'target_json' => '{invalid-json',
                    'summary' => 'Envía un restablecimiento.',
                    'payload_json' => json_encode(['reason' => 'copilot_confirmed_action'], JSON_THROW_ON_ERROR),
                    'can_execute' => true,
                    'deny_reason' => null,
                    'required_permissions' => ['system.users.send-reset', 'system.users-copilot.execute'],
                ],
            ],
            'requires_confirmation' => true,
            'references' => [],
            'meta' => [
                'module' => 'users',
                'channel' => 'web',
                'subject_user_id' => $target->id,
                'fallback' => false,
                'diagnostics_json' => null,
            ],
        ],
    ])->preventStrayPrompts();

    $this->actingAs($user)
        ->postJson(route('system.users.copilot.messages'), [
            'prompt' => 'Propón enviar un restablecimiento a este usuario.',
            'subject_user_id' => $target->id,
        ])
        ->assertSuccessful()
        ->assertJsonPath('response.intent', 'action_proposal')
        ->assertJsonPath('response.actions.0.target.email', 'invalid-gemini@example.com')
        ->assertJsonPath('response.meta.fallback', false)
        ->assertJsonPath('response.meta.diagnostics.formatter_reason', 'invalid_structured_output');
});

it('uses local gemini user-detail orchestration for selected-user follow ups', function () {
    config()->set('ai-copilot.providers.default', 'gemini');
    config()->set('ai-copilot.model', 'gemini-2.5-flash-lite');

    $user = authorizedCopilotOperator();
    $targetRole = Role::factory()->active()->create(['display_name' => 'Soporte']);
    $targetRole->syncPermissions(['system.users.view']);
    $target = User::factory()->withTwoFactor()->create([
        'name' => 'Laura Soporte',
        'email' => 'laura@example.com',
    ]);
    $target->assignRole($targetRole);

    UsersGeminiCopilotAgent::fake([
        "```json\n".json_encode([
            'answer' => 'Laura Soporte mantiene un contexto operativo claro.',
            'intent' => 'user_context',
            'cards' => [
                [
                    'kind' => 'user_context',
                    'title' => 'Resumen operativo',
                    'summary' => 'Detalle revisado con formato natural.',
                    'data' => ['ignored' => true],
                ],
            ],
            'actions' => [],
            'requires_confirmation' => false,
            'references' => [],
            'meta' => [
                'module' => 'users',
                'channel' => 'web',
                'subject_user_id' => $target->id,
                'fallback' => false,
                'diagnostics' => ['formatter' => 'text'],
            ],
        ], JSON_THROW_ON_ERROR)."\n```",
    ])->preventStrayPrompts();

    $this->actingAs($user)
        ->postJson(route('system.users.copilot.messages'), [
            'prompt' => 'Explica el acceso efectivo y el estado actual de este usuario.',
            'subject_user_id' => $target->id,
        ])
        ->assertSuccessful()
        ->assertJsonPath('response.intent', 'user_context')
        ->assertJsonPath('response.answer', 'Laura Soporte mantiene un contexto operativo claro.')
        ->assertJsonPath('response.cards.0.kind', 'user_context')
        ->assertJsonPath('response.cards.0.summary', 'Detalle revisado con formato natural.')
        ->assertJsonPath('response.cards.0.data.user.id', $target->id)
        ->assertJsonPath('response.meta.subject_user_id', $target->id)
        ->assertJsonPath('response.meta.fallback', false);

    UsersGeminiCopilotAgent::assertPrompted(function (AgentPrompt $prompt): bool {
        return $prompt->contains('Explica el acceso efectivo y el estado actual de este usuario.')
            && $prompt->contains('JSON base');
    });

    UsersCopilotAgent::assertNeverPrompted();
});

it('returns deterministic help when the gemini formatter returns invalid json', function () {
    config()->set('ai-copilot.providers.default', 'gemini');

    $user = authorizedCopilotOperator();

    UsersGeminiCopilotAgent::fake([
        '{invalid-json',
    ])->preventStrayPrompts();

    $this->actingAs($user)
        ->postJson(route('system.users.copilot.messages'), [
            'prompt' => 'Necesito ayuda.',
        ])
        ->assertSuccessful()
        ->assertJsonPath('response.intent', 'help')
        ->assertJsonPath('response.meta.fallback', false)
        ->assertJsonPath('response.meta.diagnostics.capability', 'help')
        ->assertJsonPath('response.meta.diagnostics.formatter_reason', 'missing_structured_output');
});

it('allows continuing an owned conversation while keeping transcript isolation', function () {
    $user = authorizedCopilotOperator();

    UsersCopilotAgent::fake([
        fakeCopilotResponse([
            'answer' => 'Resumen inicial.',
            'intent' => 'inform',
            'cards' => [],
        ]),
        fakeCopilotResponse([
            'answer' => 'Continué la conversación.',
            'intent' => 'inform',
            'cards' => [],
        ]),
    ])->preventStrayPrompts();

    $conversationId = $this->actingAs($user)
        ->postJson(route('system.users.copilot.messages'), [
            'prompt' => 'Resume este usuario.',
        ])
        ->assertSuccessful()
        ->json('conversation_id');

    $this->actingAs($user)
        ->postJson(route('system.users.copilot.messages'), [
            'prompt' => 'Continúa con más detalle.',
            'conversation_id' => $conversationId,
        ])
        ->assertSuccessful()
        ->assertJsonPath('conversation_id', $conversationId)
        ->assertJsonPath('response.answer', 'Continué la conversación.');

    expect(DB::table('agent_conversation_messages')->where('conversation_id', $conversationId)->count())->toBe(4);
});

it('keeps action proposals read only during message handling', function () {
    Notification::fake();

    $user = authorizedCopilotOperator();
    $target = User::factory()->active()->create([
        'name' => 'Mario Operador',
        'email' => 'mario@example.com',
    ]);

    UsersCopilotAgent::fake([
        fakeCopilotResponse([
            'answer' => 'Puedo proponerte desactivar al usuario, pero aún no se ejecuta ningún cambio.',
            'intent' => 'action_proposal',
            'cards' => [],
            'actions' => [
                [
                    'kind' => 'action_proposal',
                    'action_type' => 'deactivate',
                    'target' => [
                        'kind' => 'user',
                        'user_id' => $target->id,
                        'name' => $target->name,
                        'email' => $target->email,
                        'is_active' => true,
                    ],
                    'summary' => 'Desactiva la cuenta de Mario Operador y cierra sus sesiones activas.',
                    'payload' => [
                        'reason' => 'copilot_confirmed_action',
                    ],
                    'can_execute' => false,
                    'deny_reason' => 'Aún falta confirmación.',
                    'required_permissions' => ['system.users.deactivate', 'system.users-copilot.execute'],
                ],
            ],
        ]),
        fakeCopilotResponse([
            'answer' => 'Puedo proponer el restablecimiento, pero no se envía nada hasta confirmar.',
            'intent' => 'action_proposal',
            'cards' => [],
            'actions' => [
                [
                    'kind' => 'action_proposal',
                    'action_type' => 'send_reset',
                    'target' => [
                        'kind' => 'user',
                        'user_id' => $target->id,
                        'name' => $target->name,
                        'email' => $target->email,
                        'is_active' => true,
                    ],
                    'summary' => 'Envía un correo de restablecimiento de contraseña a Mario Operador.',
                    'payload' => [
                        'reason' => 'copilot_confirmed_action',
                    ],
                    'can_execute' => false,
                    'deny_reason' => 'Aún falta confirmación.',
                    'required_permissions' => ['system.users.send-reset', 'system.users-copilot.execute'],
                ],
            ],
        ]),
        fakeCopilotResponse([
            'answer' => 'Preparé una propuesta de alta guiada, pero no crearé al usuario hasta confirmarlo.',
            'intent' => 'action_proposal',
            'cards' => [],
            'actions' => [
                [
                    'kind' => 'action_proposal',
                    'action_type' => 'create_user',
                    'target' => [
                        'kind' => 'new_user',
                        'name' => 'Laura Copilot',
                        'email' => 'laura@example.com',
                    ],
                    'summary' => 'Preparé una propuesta de alta guiada. Revísala y confirma para crear el usuario.',
                    'payload' => [
                        'name' => 'Laura Copilot',
                        'email' => 'laura@example.com',
                        'roles' => [1],
                        'role_labels' => ['Soporte'],
                    ],
                    'can_execute' => false,
                    'deny_reason' => 'Aún falta confirmación.',
                    'required_permissions' => [
                        'system.users.create',
                        'system.users.assign-role',
                        'system.users-copilot.execute',
                    ],
                ],
            ],
        ]),
    ])->preventStrayPrompts();

    $this->actingAs($user)
        ->postJson(route('system.users.copilot.messages'), [
            'prompt' => 'Propón desactivar a Mario Operador.',
        ])
        ->assertSuccessful()
        ->assertJsonPath('response.actions.0.action_type', 'deactivate');

    $this->actingAs($user)
        ->postJson(route('system.users.copilot.messages'), [
            'prompt' => 'Propón enviar un restablecimiento a Mario Operador.',
        ])
        ->assertSuccessful()
        ->assertJsonPath('response.actions.0.action_type', 'send_reset');

    $this->actingAs($user)
        ->postJson(route('system.users.copilot.messages'), [
            'prompt' => 'Propón dar de alta a Laura Copilot.',
        ])
        ->assertSuccessful()
        ->assertJsonPath('response.actions.0.action_type', 'create_user');

    expect($target->fresh()->is_active)->toBeTrue()
        ->and(User::query()->where('email', 'laura@example.com')->exists())->toBeFalse();

    Notification::assertNothingSent();
});

it('denies continuing another users conversation', function () {
    $owner = authorizedCopilotOperator();
    $intruder = authorizedCopilotOperator();

    UsersCopilotAgent::fake([
        [
            'answer' => 'Resumen inicial.',
            'intent' => 'inform',
            'cards' => [],
            'actions' => [],
            'requires_confirmation' => false,
            'references' => [],
            'meta' => [
                'module' => 'users',
                'channel' => 'web',
                'subject_user_id' => null,
                'fallback' => false,
                'diagnostics' => null,
            ],
        ],
    ]);

    $conversationId = $this->actingAs($owner)
        ->postJson(route('system.users.copilot.messages'), [
            'prompt' => 'Resume este usuario.',
        ])
        ->json('conversation_id');

    $this->actingAs($intruder)
        ->postJson(route('system.users.copilot.messages'), [
            'prompt' => 'Quiero continuar esta conversación.',
            'conversation_id' => $conversationId,
        ])
        ->assertForbidden();
});

it('logs summarized observability data without leaking prompts or raw payloads', function () {
    $user = authorizedCopilotOperator();
    Log::spy();

    Context::add('correlation_id', 'copilot-test-correlation');

    UsersCopilotAgent::fake([
        fakeCopilotResponse([
            'answer' => 'Resumen operativo seguro.',
        ]),
    ])->preventStrayPrompts();

    $response = $this->actingAs($user)
        ->postJson(route('system.users.copilot.messages'), [
            'prompt' => 'Necesito revisar al usuario secreto@example.com',
        ]);

    $response->assertSuccessful();

    $conversationId = $response->json('conversation_id');

    Log::shouldHaveReceived('info')
        ->once()
        ->withArgs(function (string $message, array $context) use ($user): bool {
            expect($message)->toBe('ai-copilot.users.usage')
                ->and($context['actor_id'] ?? null)->toBe($user->id)
                ->and($context['module'] ?? null)->toBe('users')
                ->and($context['channel'] ?? null)->toBe('web')
                ->and($context['correlation_id'] ?? null)->toBeString()->not->toBe('')
                ->and($context)->not->toHaveKey('prompt')
                ->and($context)->not->toHaveKey('response')
                ->and($context)->not->toHaveKey('raw_payload');

            $serialized = json_encode($context, JSON_THROW_ON_ERROR);

            expect($serialized)
                ->not->toContain('Necesito revisar al usuario secreto@example.com')
                ->not->toContain('Resumen operativo seguro.')
                ->not->toContain('"password"');

            return true;
        });

    expect($conversationId)->not->toBeEmpty();

    Context::flush();
});

it('creates prompt derived titles for new conversations without a hidden extra provider call', function () {
    $user = authorizedCopilotOperator();

    expect(in_array(RemembersConversations::class, class_uses_recursive(UsersCopilotAgent::class), true))->toBeFalse();

    UsersCopilotAgent::fake([
        fakeCopilotResponse([
            'answer' => 'Consulta inicial resuelta.',
            'intent' => 'inform',
            'cards' => [],
        ]),
    ])->preventStrayPrompts();

    $response = $this->actingAs($user)
        ->postJson(route('system.users.copilot.messages'), [
            'prompt' => 'Consulta inicial para revisar permisos de usuarios',
        ]);

    $response->assertSuccessful();

    expect(DB::table('agent_conversations')->where('id', $response->json('conversation_id'))->value('title'))
        ->toBe('Consulta inicial para revisar permisos de usuarios')
        ->and((new UsersCopilotAgent(User::factory()->make()))->conversationTitleFor('Consulta inicial para revisar permisos de usuarios'))
        ->toBe('Consulta inicial para revisar permisos de usuarios');
});

it('resolves the copilot provider and model from configuration', function () {
    config()->set('ai-copilot.providers.default', 'gemini');
    config()->set('ai-copilot.model', 'gemini-2.5-flash-lite');

    $agent = new UsersCopilotAgent(User::factory()->make());

    expect($agent->provider())
        ->toBe('gemini')
        ->and($agent->model())
        ->toBe('gemini-2.5-flash-lite');
});
