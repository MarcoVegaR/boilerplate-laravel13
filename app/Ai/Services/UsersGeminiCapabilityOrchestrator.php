<?php

namespace App\Ai\Services;

use App\Ai\Support\BaseCopilotAgent;
use App\Ai\Tools\System\Users\ActivateUserTool;
use App\Ai\Tools\System\Users\CreateUserTool;
use App\Ai\Tools\System\Users\DeactivateUserTool;
use App\Ai\Tools\System\Users\GetUserDetailTool;
use App\Ai\Tools\System\Users\SearchUsersTool;
use App\Ai\Tools\System\Users\SendUserPasswordResetTool;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Laravel\Ai\Tools\Request as ToolRequest;

class UsersGeminiCapabilityOrchestrator
{
    /**
     * @return array{prompt: string, payload: array<string, mixed>}
     */
    public function prepare(BaseCopilotAgent $agent, string $prompt): array
    {
        $payload = $this->resolvePayload($agent, $prompt);

        return [
            'prompt' => $this->buildFormatterPrompt($prompt, $payload),
            'payload' => $payload,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $formattedPayload
     * @param  array<string, mixed>  $diagnostics
     * @return array<string, mixed>
     */
    public function finalizePayload(array $basePayload, ?array $formattedPayload, array $diagnostics = []): array
    {
        $payload = $this->mergeFormattedPayload($basePayload, $formattedPayload);
        $meta = is_array($payload['meta'] ?? null) ? $payload['meta'] : [];
        $existingDiagnostics = is_array($meta['diagnostics'] ?? null) ? $meta['diagnostics'] : [];

        $meta['diagnostics'] = array_filter([
            ...$existingDiagnostics,
            ...$diagnostics,
        ], static fn (mixed $value): bool => $value !== null);

        $payload['meta'] = $meta;

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    protected function resolvePayload(BaseCopilotAgent $agent, string $prompt): array
    {
        $normalizedPrompt = Str::of(Str::lower(Str::ascii($prompt)))->squish()->value();
        $subjectUser = $agent->subjectUser();
        $mentionedUser = $this->findMentionedUser($prompt);
        $contextUser = $subjectUser ?? $mentionedUser;

        if ($contextUser instanceof User && $this->looksLikeActionProposal($normalizedPrompt, ['desactiv', 'inhabilit', 'bloque'])) {
            return $this->actionPayload($agent, new DeactivateUserTool($agent->actor()), $contextUser->id, 'deactivate');
        }

        if ($contextUser instanceof User && $this->looksLikeActionProposal($normalizedPrompt, ['reactiv', 'activ'])) {
            return $this->actionPayload($agent, new ActivateUserTool($agent->actor()), $contextUser->id, 'activate');
        }

        if ($contextUser instanceof User && $this->looksLikePasswordResetProposal($normalizedPrompt)) {
            return $this->actionPayload($agent, new SendUserPasswordResetTool($agent->actor()), $contextUser->id, 'send_reset');
        }

        if ($contextUser instanceof User && ! $this->looksLikeExplicitCollectionSearch($normalizedPrompt)) {
            return $this->userDetailPayload($agent, $contextUser);
        }

        if ($this->looksLikeInactiveSearch($normalizedPrompt)) {
            return $this->inactiveUsersPayload($agent);
        }

        if ($this->looksLikeOperationalSearch($normalizedPrompt)) {
            return $this->operationalSearchPayload($agent, $normalizedPrompt);
        }

        if ($this->looksLikeCreateUserProposal($normalizedPrompt)) {
            return $this->createUserPayload($agent, $prompt);
        }

        return $this->helpPayload($agent);
    }

    protected function looksLikeInactiveSearch(string $prompt): bool
    {
        return preg_match('/\b(busca|buscar|muestra|mostrar|lista|listar|resume|resumir|revisa|revisa los|dame)\b.*\busuarios? inactiv[oa]s?\b/u', $prompt) === 1
            || preg_match('/\busuarios? inactiv[oa]s?\b.*\b(busca|mostrar|listar|resume|revisa|dame)\b/u', $prompt) === 1;
    }

    protected function looksLikeOperationalSearch(string $prompt): bool
    {
        return str_contains($prompt, 'sin roles')
            || str_contains($prompt, 'no verificados')
            || str_contains($prompt, 'no verificado')
            || str_contains($prompt, 'no tienen roles');
    }

    /**
     * @param  list<string>  $keywords
     */
    protected function looksLikeActionProposal(string $prompt, array $keywords): bool
    {
        if (
            ! str_contains($prompt, 'propon')
            && ! str_contains($prompt, 'prepara')
            && ! str_contains($prompt, 'puedo')
            && ! str_contains($prompt, 'necesito')
            && ! str_contains($prompt, 'quiero')
            && ! str_contains($prompt, 'ayudame')
            && ! str_contains($prompt, 'ayúdame')
        ) {
            return false;
        }

        foreach ($keywords as $keyword) {
            if (str_contains($prompt, $keyword)) {
                return true;
            }
        }

        return false;
    }

    protected function looksLikePasswordResetProposal(string $prompt): bool
    {
        return $this->looksLikeActionProposal($prompt, ['restablec', 'reset', 'contras']);
    }

    protected function looksLikeCreateUserProposal(string $prompt): bool
    {
        return $this->looksLikeActionProposal($prompt, ['crear usuario', 'alta', 'nuevo usuario']);
    }

    protected function looksLikeExplicitCollectionSearch(string $prompt): bool
    {
        return $this->looksLikeInactiveSearch($prompt)
            || $this->looksLikeOperationalSearch($prompt)
            || preg_match('/\b(busca|buscar|muestra|mostrar|lista|listar|resume|resumir|dame)\b.*\busuarios?\b/u', $prompt) === 1;
    }

    protected function findMentionedUser(string $prompt): ?User
    {
        if (preg_match('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', $prompt, $matches) === 1) {
            $user = User::query()->where('email', Str::lower($matches[0]))->first();

            if ($user instanceof User) {
                return $user;
            }
        }

        $normalizedPrompt = Str::of(Str::lower(Str::ascii($prompt)))->squish()->value();

        return User::query()
            ->select(['id', 'name', 'email', 'is_active'])
            ->get()
            ->sortByDesc(static fn (User $user): int => mb_strlen($user->name))
            ->first(function (User $user) use ($normalizedPrompt): bool {
                $normalizedName = Str::of(Str::lower(Str::ascii($user->name)))->squish()->value();

                return $normalizedName !== ''
                    && mb_strlen($normalizedName) >= 4
                    && str_contains($normalizedPrompt, $normalizedName);
            });
    }

    /**
     * @return array<string, mixed>
     */
    protected function inactiveUsersPayload(BaseCopilotAgent $agent): array
    {
        $results = $this->runJsonTool(new SearchUsersTool($agent->actor()), [
            'status' => 'inactive',
        ]);

        $count = (int) ($results['count'] ?? 0);
        $users = Arr::wrap($results['users'] ?? []);
        $namedUsers = collect($users)
            ->pluck('name')
            ->filter(fn (mixed $name): bool => is_string($name) && $name !== '')
            ->take(3)
            ->values()
            ->all();

        $answer = $count === 0
            ? 'No encontré usuarios inactivos con los datos actuales.'
            : 'Encontré '.$count.' usuario'.($count === 1 ? '' : 's').' inactivo'.($count === 1 ? '' : 's').'. '
                .$this->joinNaturalList($namedUsers)
                .$this->inactiveUsersStatusSuffix($users);

        return $this->makePayload(
            agent: $agent,
            answer: rtrim($answer),
            intent: 'search_results',
            cards: [[
                'kind' => 'search_results',
                'title' => 'Usuarios inactivos',
                'summary' => $count === 0
                    ? 'No hay usuarios inactivos para revisar.'
                    : 'Hay '.$count.' usuario'.($count === 1 ? '' : 's').' inactivo'.($count === 1 ? '' : 's').' en el resultado actual.',
                'data' => $results,
            ]],
            references: $this->referencesFromUsers($users),
            diagnostics: [
                'execution' => 'local_capability_orchestrator',
                'capability' => 'inactive_search',
                'formatter' => 'gemini_text_json',
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function operationalSearchPayload(BaseCopilotAgent $agent, string $prompt): array
    {
        $filters = ['status' => 'all'];
        $title = 'Busqueda operativa de usuarios';

        if (str_contains($prompt, 'sin roles') || str_contains($prompt, 'no tienen roles')) {
            $filters['has_roles'] = false;
            $title = 'Usuarios sin roles';
        }

        if (str_contains($prompt, 'no verificados') || str_contains($prompt, 'no verificado')) {
            $filters['email_verified'] = false;
            $title = 'Usuarios no verificados';
        }

        $results = $this->runJsonTool(new SearchUsersTool($agent->actor()), $filters);
        $count = (int) ($results['count'] ?? 0);

        return $this->makePayload(
            agent: $agent,
            answer: $count === 0
                ? 'No encontré usuarios para ese criterio operativo.'
                : 'Encontré '.$count.' usuario'.($count === 1 ? '' : 's').' para ese criterio operativo.',
            intent: 'search_results',
            cards: [[
                'kind' => 'search_results',
                'title' => $title,
                'summary' => $count === 0
                    ? 'No hubo coincidencias.'
                    : 'Resultado operativo listo para revisar.',
                'data' => $results,
            ]],
            references: $this->referencesFromUsers(Arr::wrap($results['users'] ?? [])),
            diagnostics: [
                'execution' => 'local_capability_orchestrator',
                'capability' => 'operational_search',
                'formatter' => 'gemini_text_json',
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function userDetailPayload(BaseCopilotAgent $agent, User $user): array
    {
        $detail = $this->runJsonTool(new GetUserDetailTool($agent->actor()), [
            'user_id' => $user->id,
            'include_access' => true,
        ]);

        if (! ($detail['found'] ?? false)) {
            return $this->makePayload(
                agent: $agent,
                answer: 'No encontré el usuario solicitado para revisar su estado.',
                intent: 'inform',
                cards: [],
                diagnostics: [
                    'execution' => 'local_capability_orchestrator',
                    'capability' => 'user_detail',
                    'formatter' => 'gemini_text_json',
                    'result' => 'not_found',
                ],
            );
        }

        $userData = Arr::get($detail, 'user', []);
        $roles = Arr::wrap($detail['roles'] ?? []);
        $permissionGroups = array_keys(Arr::wrap($detail['effective_permissions'] ?? []));

        $status = ($userData['is_active'] ?? false) ? 'activo' : 'inactivo';
        $verification = ($userData['email_verified'] ?? false) ? 'correo verificado' : 'correo no verificado';
        $twoFactor = ($userData['two_factor_enabled'] ?? false) ? '2FA activo' : '2FA no configurado';

        return $this->makePayload(
            agent: $agent,
            answer: sprintf(
                '%s está %s, tiene %s y %s. Roles asignados: %d. Grupos de permisos efectivos: %d.',
                $userData['name'] ?? 'El usuario',
                $status,
                $verification,
                $twoFactor,
                count($roles),
                count($permissionGroups),
            ),
            intent: 'user_context',
            cards: [[
                'kind' => 'user_context',
                'title' => 'Contexto del usuario',
                'summary' => 'Estado operativo, roles y acceso efectivo consolidados.',
                'data' => $detail,
            ]],
            references: Arr::wrap($userData['references'] ?? []),
            diagnostics: [
                'execution' => 'local_capability_orchestrator',
                'capability' => 'user_detail',
                'formatter' => 'gemini_text_json',
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function actionPayload(BaseCopilotAgent $agent, object $tool, int $userId, string $capability): array
    {
        $result = $this->runJsonTool($tool, ['user_id' => $userId]);
        $action = is_array($result['action'] ?? null) ? $result['action'] : null;

        if ($action === null) {
            return $this->makePayload(
                agent: $agent,
                answer: (string) ($result['message'] ?? 'No pude preparar la propuesta solicitada.'),
                intent: 'inform',
                cards: [],
                diagnostics: [
                    'execution' => 'local_capability_orchestrator',
                    'capability' => $capability,
                    'formatter' => 'gemini_text_json',
                    'result' => 'missing_action',
                ],
            );
        }

        $canExecute = (bool) ($action['can_execute'] ?? false);
        $answer = $canExecute
            ? 'Preparé una propuesta lista para confirmación. Revísala antes de ejecutarla.'
            : 'Preparé una propuesta no ejecutable por ahora. Revisa el motivo indicado antes de continuar.';

        return $this->makePayload(
            agent: $agent,
            answer: $answer,
            intent: 'action_proposal',
            cards: [],
            actions: [$action],
            requiresConfirmation: $canExecute,
            references: $this->referencesFromAction($action),
            diagnostics: [
                'execution' => 'local_capability_orchestrator',
                'capability' => $capability,
                'formatter' => 'gemini_text_json',
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function createUserPayload(BaseCopilotAgent $agent, string $prompt): array
    {
        preg_match('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', $prompt, $emailMatch);
        $name = Str::of($prompt)
            ->after(' para ')
            ->before(',')
            ->trim()
            ->value();

        $result = $this->runJsonTool(new CreateUserTool($agent->actor()), [
            'name' => $name !== '' ? $name : null,
            'email' => $emailMatch[0] ?? null,
            'roles' => $this->extractRoleHints($prompt),
        ]);

        $action = is_array($result['action'] ?? null) ? $result['action'] : null;

        return $this->makePayload(
            agent: $agent,
            answer: $action === null
                ? (string) ($result['message'] ?? 'No pude preparar el alta guiada solicitada.')
                : 'Preparé una propuesta de alta guiada. Completa los campos faltantes antes de confirmar.',
            intent: $action === null ? 'inform' : 'action_proposal',
            cards: $action === null ? [] : [[
                'kind' => 'notice',
                'title' => 'Alta guiada',
                'summary' => 'Revisa los campos detectados y los faltantes antes de confirmar.',
                'data' => [
                    'missing_fields' => Arr::wrap($result['missing_fields'] ?? []),
                ],
            ]],
            actions: $action === null ? [] : [$action],
            requiresConfirmation: (bool) Arr::get($action, 'can_execute', false),
            diagnostics: [
                'execution' => 'local_capability_orchestrator',
                'capability' => 'create_user',
                'formatter' => 'gemini_text_json',
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function helpPayload(BaseCopilotAgent $agent): array
    {
        return $this->makePayload(
            agent: $agent,
            answer: 'Puedo buscar usuarios inactivos, revisar el estado y acceso efectivo de un usuario con contexto seleccionado, y preparar propuestas seguras como activar, desactivar, enviar restablecimiento o alta guiada cuando haya datos suficientes.',
            intent: 'help',
            cards: [[
                'kind' => 'notice',
                'title' => 'Capacidades disponibles',
                'summary' => 'Consultas operativas del modulo de usuarios disponibles sin ejecutar cambios.',
                'data' => [
                    'supported_queries' => [
                        'Busca usuarios inactivos y resume su estado actual',
                        'Explica el acceso efectivo del usuario seleccionado',
                        'Propón desactivar al usuario seleccionado',
                        'Propón enviar un restablecimiento al usuario seleccionado',
                    ],
                ],
            ]],
            diagnostics: [
                'execution' => 'local_capability_orchestrator',
                'capability' => 'help',
                'formatter' => 'gemini_text_json',
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function buildFormatterPrompt(string $originalPrompt, array $payload): string
    {
        $instructions = "Consulta original del operador:\n{$originalPrompt}\n\n"
            ."El backend ya ejecuto capacidades locales seguras del modulo de usuarios y construyo un JSON canonico valido.\n"
            ."Tu trabajo es solo mejorar la redaccion de los textos visibles sin cambiar hechos, permisos, ids, acciones, referencias, intent ni meta.\n\n"
            ."Reglas estrictas:\n"
            ."- Devuelve solo un objeto JSON valido.\n"
            ."- Conserva exactamente la misma estructura y los mismos valores de machine data.\n"
            ."- Solo puedes ajustar answer, cards[].title, cards[].summary y actions[].summary para que suenen mas naturales.\n"
            ."- No agregues Markdown ni comentarios.\n\n"
            .'JSON base:';

        return $instructions."\n".json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    /**
     * @param  array<string, mixed>|null  $formattedPayload
     * @return array<string, mixed>
     */
    protected function mergeFormattedPayload(array $basePayload, ?array $formattedPayload): array
    {
        if (! is_array($formattedPayload)) {
            return $basePayload;
        }

        if (is_string($formattedPayload['answer'] ?? null) && $formattedPayload['answer'] !== '') {
            $basePayload['answer'] = $formattedPayload['answer'];
        }

        foreach (Arr::wrap($basePayload['cards'] ?? []) as $index => $card) {
            $formattedCard = Arr::get($formattedPayload, "cards.{$index}");

            if (! is_array($formattedCard) || ($formattedCard['kind'] ?? null) !== ($card['kind'] ?? null)) {
                continue;
            }

            if (is_string($formattedCard['title'] ?? null)) {
                $basePayload['cards'][$index]['title'] = $formattedCard['title'];
            }

            if (is_string($formattedCard['summary'] ?? null)) {
                $basePayload['cards'][$index]['summary'] = $formattedCard['summary'];
            }
        }

        foreach (Arr::wrap($basePayload['actions'] ?? []) as $index => $action) {
            $formattedAction = Arr::get($formattedPayload, "actions.{$index}");

            if (! is_array($formattedAction) || ($formattedAction['action_type'] ?? null) !== ($action['action_type'] ?? null)) {
                continue;
            }

            if (is_string($formattedAction['summary'] ?? null)) {
                $basePayload['actions'][$index]['summary'] = $formattedAction['summary'];
            }
        }

        return $basePayload;
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    protected function runJsonTool(object $tool, array $arguments): array
    {
        return json_decode($tool->handle(new ToolRequest($arguments)), true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * @param  list<array<string, mixed>>  $users
     */
    protected function inactiveUsersStatusSuffix(array $users): string
    {
        if ($users === []) {
            return '';
        }

        $unverified = collect($users)->where('email_verified', false)->count();
        $withTwoFactor = collect($users)->where('two_factor_enabled', true)->count();

        return ' '.$unverified.' sin correo verificado y '.$withTwoFactor.' con 2FA activo en esta muestra.';
    }

    /**
     * @param  list<string>  $values
     */
    protected function joinNaturalList(array $values): string
    {
        if ($values === []) {
            return '';
        }

        if (count($values) === 1) {
            return $values[0].'.';
        }

        $last = array_pop($values);

        return implode(', ', $values).' y '.$last.'.';
    }

    /**
     * @param  list<array<string, mixed>>  $users
     * @return list<array{label: string, href: string|null}>
     */
    protected function referencesFromUsers(array $users): array
    {
        return collect($users)
            ->filter(fn (mixed $user): bool => is_array($user))
            ->take(3)
            ->map(fn (array $user): array => [
                'label' => (string) ($user['name'] ?? 'Abrir usuario'),
                'href' => is_string($user['show_href'] ?? null) ? $user['show_href'] : null,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $action
     * @return list<array{label: string, href: string|null}>
     */
    protected function referencesFromAction(array $action): array
    {
        $target = Arr::wrap($action['target'] ?? []);
        $userId = Arr::get($target, 'user_id');

        if (! is_int($userId)) {
            return [];
        }

        return [[
            'label' => 'Abrir perfil del usuario',
            'href' => route('system.users.show', ['user' => $userId], absolute: false),
        ]];
    }

    /**
     * @return list<string>
     */
    protected function extractRoleHints(string $prompt): array
    {
        return collect(preg_split('/[,;]+/', $prompt) ?: [])
            ->map(fn (string $part): string => trim($part))
            ->filter(fn (string $part): bool => str_contains(Str::lower($part), 'rol '))
            ->map(fn (string $part): string => trim(Str::after(Str::lower($part), 'rol ')))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $cards
     * @param  list<array<string, mixed>>  $actions
     * @param  list<array{label: string, href: string|null}>  $references
     * @param  array<string, mixed>  $diagnostics
     * @return array<string, mixed>
     */
    protected function makePayload(
        BaseCopilotAgent $agent,
        string $answer,
        string $intent,
        array $cards,
        array $actions = [],
        bool $requiresConfirmation = false,
        array $references = [],
        array $diagnostics = [],
    ): array {
        return [
            'answer' => $answer,
            'intent' => $intent,
            'cards' => $cards,
            'actions' => $actions,
            'requires_confirmation' => $requiresConfirmation,
            'references' => $references,
            'meta' => [
                'module' => $agent->module(),
                'channel' => $agent->channel(),
                'subject_user_id' => $agent->subjectUser()?->id,
                'fallback' => false,
                'diagnostics' => $diagnostics === [] ? null : $diagnostics,
            ],
        ];
    }
}
