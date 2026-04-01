<?php

use App\Models\Role;
use App\Models\User;
use Database\Seeders\AccessModulePermissionsSeeder;
use Database\Seeders\AiCopilotPermissionsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Vite;
use Tests\Support\CopilotBrowserFake;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class);

beforeEach(function () {
    CopilotBrowserFake::clear();

    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(AccessModulePermissionsSeeder::class);
    $this->seed(AiCopilotPermissionsSeeder::class);

    config(['ai-copilot.enabled' => true]);

    $hotPath = storage_path('framework/testing/vite.hot');
    if (file_exists($hotPath)) {
        unlink($hotPath);
    }
    Vite::useHotFile($hotPath);
});

afterEach(function () {
    CopilotBrowserFake::clear();
});

function browserCopilotOperator(array $permissions): User
{
    $role = Role::factory()->active()->create();
    $role->syncPermissions($permissions);

    $user = User::factory()->withTwoFactor()->create();
    $user->assignRole($role);

    return $user;
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function browserCopilotResponse(array $overrides = []): array
{
    return array_replace_recursive([
        'answer' => 'Preparé una propuesta para revisar.',
        'intent' => 'action_proposal',
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
    ], $overrides);
}

it('shows the copilot entrypoint, filters empty state prompts, and disables confirmation for proposal only actions', function () {
    $this->app['env'] = 'local';

    $operator = browserCopilotOperator([
        'system.users.view',
        'system.users.send-reset',
        'system.users-copilot.view',
        'system.users-copilot.execute',
    ]);
    $target = User::factory()->create([
        'name' => 'Reset Target',
        'email' => 'reset-target@example.test',
    ]);

    CopilotBrowserFake::write([
        browserCopilotResponse([
            'answer' => 'Puedo proponer el restablecimiento, pero esta propuesta no es ejecutable todavía.',
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
                    'summary' => 'Envía un correo de restablecimiento de contraseña a Reset Target.',
                    'payload' => [
                        'reason' => 'copilot_confirmed_action',
                    ],
                    'can_execute' => false,
                    'deny_reason' => 'El backend marcó esta propuesta como solo lectura.',
                    'required_permissions' => ['system.users.send-reset', 'system.users-copilot.execute'],
                ],
            ],
        ]),
    ]);

    $this->actingAs($operator);

    $page = visit(route('system.users.index', [], false));

    $page->assertSee('Copiloto')
        ->click('Copiloto')
        ->assertSee('Copiloto de usuarios')
        ->assertSee('Buscar usuarios inactivos')
        ->assertSee('Proponer un restablecimiento')
        ->assertDontSee('Explicar accesos efectivos')
        ->assertPresent('@copilot-prompt')
        ->assertVisible('@copilot-prompt')
        ->type('@copilot-prompt', 'Propón un restablecimiento para Reset Target')
        ->assertButtonEnabled('@copilot-submit')
        ->press('Enviar')
        ->assertSee('Puedo proponer el restablecimiento, pero esta propuesta no es ejecutable todavía.')
        ->assertSee('Solo propuesta')
        ->assertButtonDisabled('@copilot-action-send_reset')
        ->assertNoJavaScriptErrors();
});

it('confirms an executable copilot proposal from the browser flow', function () {
    $this->app['env'] = 'local';

    Notification::fake();

    $operator = browserCopilotOperator([
        'system.users.view',
        'system.users.send-reset',
        'system.users-copilot.view',
        'system.users-copilot.execute',
    ]);
    $target = User::factory()->create([
        'name' => 'Resettable User',
        'email' => 'resettable@example.test',
    ]);

    CopilotBrowserFake::write([
        browserCopilotResponse([
            'answer' => 'La propuesta está lista para confirmar.',
            'requires_confirmation' => true,
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
                    'summary' => 'Envía un correo de restablecimiento de contraseña a Resettable User.',
                    'payload' => [
                        'reason' => 'copilot_confirmed_action',
                    ],
                    'can_execute' => true,
                    'deny_reason' => null,
                    'required_permissions' => ['system.users.send-reset', 'system.users-copilot.execute'],
                ],
            ],
        ]),
    ]);

    $this->actingAs($operator);

    $page = visit(route('system.users.index', [], false));

    $page->click('Copiloto')
        ->assertPresent('@copilot-prompt')
        ->assertVisible('@copilot-prompt')
        ->type('@copilot-prompt', 'Necesito enviar un restablecimiento a Resettable User')
        ->assertButtonEnabled('@copilot-submit')
        ->press('Enviar')
        ->assertSee('La propuesta está lista para confirmar.')
        ->assertSee('Lista para confirmar')
        ->assertButtonEnabled('@copilot-action-send_reset')
        ->click('@copilot-action-send_reset')
        ->assertSee('Confirmar restablecimiento')
        ->assertButtonEnabled('@copilot-confirm-submit')
        ->click('@copilot-confirm-submit')
        ->assertSee('Correo de restablecimiento de contraseña enviado exitosamente.')
        ->assertSee('Resettable User · resettable@example.test')
        ->assertNoJavaScriptErrors();

    Notification::assertSentTo($target, ResetPassword::class);
});
