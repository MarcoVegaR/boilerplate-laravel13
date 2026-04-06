<?php

namespace App\Ai\Services;

use App\Ai\Support\CopilotConversationSnapshot;
use App\Ai\Support\UsersCopilotDomainLexicon;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class UsersCopilotRequestPlanner
{
    /**
     * @return array{
     *   request_normalization: string,
     *   intent_family: 'read_metrics'|'read_search'|'read_detail'|'read_explain'|'action_proposal'|'help'|'out_of_scope'|'ambiguous',
     *   capability_key: ?string,
     *   filters: array{query: ?string, status: 'active'|'inactive'|'all'|null, role: ?string, access_profile: ?string, permission: ?string, email_verified: ?bool, has_roles: ?bool},
     *   resolved_entity: array{type: 'user', id: int, label: string}|null,
     *   missing_slots: list<string>,
     *   clarification_state: array{reason: string, question: string, options?: list<array{label: string, value: string}>}|null,
     *   proposal_vs_execute: 'proposal'|'execute'|'none'
     * }
     */
    public function plan(string $prompt, CopilotConversationSnapshot $snapshot, ?User $subjectUser = null): array
    {
        $normalized = $this->normalizePrompt($prompt);

        if ($this->looksLikeConversationContinuation($normalized)) {
            return $this->helpPlan($normalized);
        }

        if ($this->looksLikeInformationalPrompt($normalized)) {
            return $this->helpPlan($normalized);
        }

        if ($clarificationResolution = $this->resolvePendingClarification($normalized, $snapshot)) {
            return $clarificationResolution;
        }

        if ($matrixPlan = $this->matchConfiguredMatrix($normalized, $snapshot)) {
            return $matrixPlan;
        }

        if ($actionExplainPlan = $this->matchActionExplanationIntent($normalized)) {
            return $actionExplainPlan;
        }

        if ($subjectUser === null
            && ! $this->isCreateUserProposal($normalized)
            && ! $this->looksLikeExplicitCollectionSearch($normalized)
            && ! $this->looksLikeActionExplanationPrompt($normalized)
            && $this->looksLikeExplicitDetailPrompt($normalized)
            && ($entityPlan = $this->resolveEntityDrivenPlan($normalized, $snapshot))) {
            return $entityPlan;
        }

        if ($followUpPlan = $this->resolveFollowUp($normalized, $snapshot)) {
            return $followUpPlan;
        }

        if ($mixedPlan = $this->matchMixedMetricsSearchIntent($normalized)) {
            return $mixedPlan;
        }

        if ($rolesCatalogPlan = $this->matchRolesCatalogIntent($normalized)) {
            return $rolesCatalogPlan;
        }

        if ($metricsPlan = $this->matchMetricsIntent($normalized)) {
            return $metricsPlan;
        }

        if ($permissionSearchPlan = $this->matchPermissionSearchIntent($normalized)) {
            return $permissionSearchPlan;
        }

        if ($this->looksLikeSearch($normalized)) {
            return $this->searchPlan($normalized);
        }

        if ($subjectUser === null && ! $this->isCreateUserProposal($normalized) && ($entityPlan = $this->resolveEntityDrivenPlan($normalized, $snapshot))) {
            return $entityPlan;
        }

        if ($subjectUser instanceof User && ! $this->looksLikeActionProposal($normalized)) {
            if ($this->looksLikeCapabilitiesSummaryPrompt($normalized)) {
                return $this->capabilitiesSummaryPlan($normalized, $subjectUser);
            }

            if ($permission = UsersCopilotDomainLexicon::permissionNameForIntent($normalized)) {
                return $this->permissionExplainPlan($normalized, $subjectUser, $permission);
            }

            return $this->detailPlan($normalized, $subjectUser);
        }

        if ($this->looksLikeActionProposal($normalized)) {
            return $this->actionPlan($normalized, $subjectUser);
        }

        return $this->helpPlan($normalized);
    }

    protected function normalizePrompt(string $prompt): string
    {
        return UsersCopilotDomainLexicon::normalize($prompt);
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function matchConfiguredMatrix(string $normalized, CopilotConversationSnapshot $snapshot): ?array
    {
        foreach (['deterministic', 'extended'] as $matrix) {
            foreach (Arr::wrap(config("ai-copilot.planning.matrices.{$matrix}")) as $case) {
                if (($case['prompt'] ?? null) !== $normalized) {
                    continue;
                }

                if (($case['requires_snapshot'] ?? false) === true && ! $this->hasSnapshotContext($snapshot)) {
                    return $this->clarificationPlan(
                        normalized: $normalized,
                        reason: 'missing_context',
                        question: 'Necesito el contexto previo o un criterio mas especifico para continuar.',
                    );
                }

                if (! is_string($case['capability_key'] ?? null) && ($case['intent_family'] ?? null) === 'ambiguous') {
                    return $this->clarificationPlan(
                        normalized: $normalized,
                        reason: 'ambiguous_target',
                        question: 'Necesito que aclares a que usuario o subconjunto te refieres.',
                    );
                }

                if (! is_string($case['capability_key'] ?? null)) {
                    if ($this->looksLikeCountFollowUp($normalized)) {
                        return $this->countFollowUpPlan($normalized, $snapshot);
                    }

                    return $this->clarificationPlan(
                        normalized: $normalized,
                        reason: 'missing_context',
                        question: 'Necesito el resultado anterior o un criterio explicito para responder con seguridad.',
                    );
                }

                return $this->planFromCapability($normalized, $case['capability_key']);
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function resolveFollowUp(string $normalized, CopilotConversationSnapshot $snapshot): ?array
    {
        if (! $snapshot->hasContext()) {
            return null;
        }

        if ($this->looksLikeCountFollowUp($normalized)) {
            return $this->countFollowUpPlan($normalized, $snapshot);
        }

        if ($this->looksLikeSubsetRefinement($normalized)) {
            return $this->subsetFollowUpPlan($normalized, $snapshot);
        }

        if ($this->looksLikeEntityReference($normalized)) {
            return $this->entityReferencePlan($normalized, $snapshot);
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function resolvePendingClarification(string $normalized, CopilotConversationSnapshot $snapshot): ?array
    {
        $clarification = $snapshot->pendingClarification();

        if (! is_array($clarification)) {
            return null;
        }

        if ($this->looksLikeNewIntent($normalized)) {
            return null;
        }

        $options = array_values(array_filter(
            Arr::wrap($clarification['options'] ?? []),
            static fn (mixed $option): bool => is_array($option),
        ));

        if ($options === []) {
            return $this->clarificationPlan(
                normalized: $normalized,
                reason: (string) ($clarification['reason'] ?? 'missing_context'),
                question: (string) ($clarification['question'] ?? 'Necesito una aclaracion para continuar.'),
            );
        }

        $hint = $this->clarificationHint($normalized);

        $matches = array_values(array_filter($options, fn (array $option): bool => $this->clarificationOptionMatchesPrompt($option, $normalized)
            || ($hint !== null && $this->clarificationOptionMatchesPrompt($option, $hint))));

        if (count($matches) !== 1 && $hint !== null) {
            $matchedOption = $this->resolveClarificationOptionFromUserHint($hint, $options);

            if ($matchedOption !== null) {
                return $this->planFromClarificationOption($normalized, $matchedOption);
            }
        }

        if (count($matches) !== 1) {
            return $this->clarificationPlan(
                normalized: $normalized,
                reason: (string) ($clarification['reason'] ?? 'ambiguous_target'),
                question: (string) ($clarification['question'] ?? 'Necesito una aclaracion para continuar.'),
            );
        }

        return $this->planFromClarificationOption($normalized, $matches[0]);
    }

    protected function looksLikeNewIntent(string $normalized): bool
    {
        return preg_match('/\b(busca|buscar|lista|listar|muestra|mostrar|explica|como\s+puedo|cuantos|dame|encuentra|crear|crea)\b/u', $normalized) === 1;
    }

    /**
     * @param  array<string, mixed>  $option
     * @return array<string, mixed>
     */
    protected function planFromClarificationOption(string $normalized, array $option): array
    {
        $capabilityKey = is_string($option['capability_key'] ?? null)
            ? $option['capability_key']
            : 'users.detail';

        $intentFamily = is_string($option['intent_family'] ?? null)
            ? $option['intent_family']
            : (($capabilityKey === 'users.detail') ? 'read_detail' : 'action_proposal');

        return [
            'request_normalization' => $normalized,
            'intent_family' => $intentFamily,
            'capability_key' => $capabilityKey,
            'filters' => $this->emptyFilters(),
            'resolved_entity' => is_array($option['resolved_entity'] ?? null) ? $option['resolved_entity'] : null,
            'missing_slots' => [],
            'clarification_state' => null,
            'proposal_vs_execute' => $intentFamily === 'action_proposal' ? 'proposal' : 'execute',
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function countFollowUpPlan(string $normalized, CopilotConversationSnapshot $snapshot): ?array
    {
        $metricSnapshot = $snapshot->lastMetricsSnapshot();

        if (is_array($metricSnapshot) && is_string($metricSnapshot['capability_key'] ?? null)) {
            return $this->planFromCapability($normalized, $metricSnapshot['capability_key']);
        }

        if ($snapshot->lastResultCount() === null) {
            return $this->clarificationPlan(
                normalized: $normalized,
                reason: 'missing_context',
                question: 'Necesito el resultado anterior o un criterio explicito para decirte cuantos son.',
            );
        }

        return [
            'request_normalization' => $normalized,
            'intent_family' => 'read_metrics',
            'capability_key' => 'users.snapshot.result_count',
            'filters' => $snapshot->lastFilters(),
            'resolved_entity' => null,
            'missing_slots' => [],
            'clarification_state' => null,
            'proposal_vs_execute' => 'execute',
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function subsetFollowUpPlan(string $normalized, CopilotConversationSnapshot $snapshot): ?array
    {
        if ($snapshot->lastCapabilityKey() !== 'users.search' && $snapshot->lastFilters() === []) {
            return $this->clarificationPlan(
                normalized: $normalized,
                reason: 'missing_context',
                question: 'Necesito una busqueda previa para poder refinar ese subconjunto.',
            );
        }

        $filters = [
            ...$this->emptyFilters(),
            ...$snapshot->lastFilters(),
        ];

        if (str_contains($normalized, 'inactivo')) {
            $filters['status'] = 'inactive';
        }

        if (str_contains($normalized, 'activo') && ! str_contains($normalized, 'inactivo')) {
            $filters['status'] = 'active';
        }

        if (str_contains($normalized, 'no verificado')) {
            $filters['email_verified'] = false;
        }

        if (str_contains($normalized, 'verificado') && ! str_contains($normalized, 'no verificado')) {
            $filters['email_verified'] = true;
        }

        if ($this->extractHasRolesFilter($normalized) === false) {
            $filters['has_roles'] = false;
        }

        if ($this->extractHasRolesFilter($normalized) === true) {
            $filters['has_roles'] = true;
        }

        $roleFilter = $this->extractRoleFilter($normalized);

        if ($roleFilter !== null) {
            $filters['role'] = $roleFilter;
        }

        return [
            'request_normalization' => $normalized,
            'intent_family' => 'read_search',
            'capability_key' => 'users.search',
            'filters' => $filters,
            'resolved_entity' => null,
            'missing_slots' => [],
            'clarification_state' => null,
            'proposal_vs_execute' => 'execute',
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function entityReferencePlan(string $normalized, CopilotConversationSnapshot $snapshot): ?array
    {
        $resolvedEntity = $snapshot->resolvedEntity();
        $resolvedUserId = is_array($resolvedEntity) && (($resolvedEntity['type'] ?? null) === 'user')
            ? ($resolvedEntity['id'] ?? null)
            : null;
        $resolvedUserId ??= $snapshot->singleResultUserId();

        if (! is_int($resolvedUserId)) {
            return $this->clarificationPlan(
                normalized: $normalized,
                reason: 'missing_context',
                question: 'Necesito que me indiques que usuario quieres revisar o una busqueda previa con un unico resultado.',
            );
        }

        $resolvedUser = User::query()->find($resolvedUserId);

        if (! $resolvedUser instanceof User) {
            return $this->clarificationPlan(
                normalized: $normalized,
                reason: 'missing_context',
                question: 'Perdi el contexto del usuario anterior. Indica de nuevo el usuario que quieres revisar.',
            );
        }

        return $this->looksLikeActionProposal($normalized)
            ? $this->actionPlan($normalized, $resolvedUser)
            : $this->detailPlan($normalized, $resolvedUser);
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function resolveEntityDrivenPlan(string $normalized, CopilotConversationSnapshot $snapshot): ?array
    {
        if (! $this->looksLikeUserTargetingPrompt($normalized)) {
            return null;
        }

        if ($this->looksLikeEntityReference($normalized)) {
            return $this->entityReferencePlan($normalized, $snapshot);
        }

        $query = $this->extractEntitySearchQuery($normalized);

        if ($query === null) {
            return $this->clarificationPlan(
                normalized: $normalized,
                reason: 'missing_target',
                question: 'Necesito que me indiques el usuario concreto que quieres revisar.',
                missingSlots: ['user_id'],
            );
        }

        $matches = $this->candidateUsersFor($query);

        if ($matches->count() > 1) {
            return $this->ambiguousUserClarificationPlan(
                $normalized,
                $matches,
                $this->looksLikeActionProposal($normalized) ? $this->actionCapabilityKey($normalized) : 'users.detail',
            );
        }

        if ($matches->count() === 1) {
            $match = $matches->first();

            if ($this->looksLikeActionProposal($normalized)) {
                return $this->actionPlan($normalized, $match);
            }

            if ($this->looksLikeCapabilitiesSummaryPrompt($normalized)) {
                return $this->capabilitiesSummaryPlan($normalized, $match);
            }

            if ($permission = UsersCopilotDomainLexicon::permissionNameForIntent($normalized)) {
                return $this->permissionExplainPlan($normalized, $match, $permission);
            }

            return $this->detailPlan($normalized, $match);
        }

        return $this->clarificationPlan(
            normalized: $normalized,
            reason: 'missing_target',
            question: 'No pude identificar un usuario unico con esa referencia. Indica el nombre completo o el correo.',
            missingSlots: ['user_id'],
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function matchMetricsIntent(string $normalized): ?array
    {
        if ($this->extractAccessProfileFilter($normalized) === 'administrative_access'
            && preg_match('/\b(cantidad|cuantos|cuantas|hay)\b/u', $normalized) === 1
            && preg_match('/\b(quienes\s+son|quien(es)?\s+son)\b/u', $normalized) === 1) {
            $searchPlan = $this->searchPlan($normalized);

            return [
                ...$searchPlan,
                'request_normalization' => $normalized,
                'capability_key' => 'users.mixed.metrics_search',
            ];
        }

        if ($this->looksLikeCombinedMetricsQuery($normalized)) {
            return $this->combinedMetricsPlan($normalized);
        }

        $capabilityKey = match (true) {
            str_contains($normalized, 'rol mas comun') => 'users.metrics.most_common_role',
            $this->extractAccessProfileFilter($normalized) === 'administrative_access' && preg_match('/\b(cantidad|cuantos|cuantas|total|tenemos|hay)\b/u', $normalized) === 1 => 'users.metrics.admin_access',
            str_contains($normalized, 'distribucion de roles') => 'users.metrics.role_distribution',
            $this->extractHasRolesFilter($normalized) === false => 'users.metrics.without_roles',
            $this->extractHasRolesFilter($normalized) === true => 'users.metrics.with_roles',
            str_contains($normalized, 'no estan verificados'), str_contains($normalized, 'no verificados') => 'users.metrics.unverified',
            str_contains($normalized, 'estan verificados'), str_contains($normalized, 'verificados') => 'users.metrics.verified',
            preg_match('/\bcuantos\s+activos\s+hay\b/u', $normalized) === 1 => 'users.metrics.active',
            preg_match('/\bcuantos\s+inactivos\s+hay\b/u', $normalized) === 1 => 'users.metrics.inactive',
            str_contains($normalized, 'usuarios inactivos') && str_contains($normalized, 'cuantos') => 'users.metrics.inactive',
            str_contains($normalized, 'usuarios activos') && str_contains($normalized, 'cuantos') => 'users.metrics.active',
            str_contains($normalized, 'cuantos usuarios hay'), str_contains($normalized, 'total actual de usuarios'), str_contains($normalized, 'cantidad de usuarios') => 'users.metrics.total',
            default => null,
        };

        if ($capabilityKey !== null) {
            return $this->planFromCapability($normalized, $capabilityKey);
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function matchPermissionSearchIntent(string $normalized): ?array
    {
        $permission = UsersCopilotDomainLexicon::permissionNameForIntent($normalized);

        if ($permission === null) {
            return null;
        }

        if (preg_match('/\b(quien|quienes|usuarios?|list(a|ame)|muestrame|muestra|dame)\b/u', $normalized) === 1
            && preg_match('/\b(puede(n)?|pueda(n)?|tiene(n)?\s+permisos?|con\s+permisos?)\b/u', $normalized) === 1) {
            $plan = $this->searchPlan($normalized);
            $plan['filters']['permission'] = $permission;

            return $plan;
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function matchActionExplanationIntent(string $normalized): ?array
    {
        if (! str_contains($normalized, 'por que')) {
            return null;
        }

        $actionType = $this->extractActionTypeForExplanation($normalized);

        if ($actionType === null) {
            return null;
        }

        preg_match_all('/[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}/', $normalized, $emailMatches);
        $emails = array_values(array_unique($emailMatches[0] ?? []));

        if (count($emails) < 2) {
            return null;
        }

        $subject = User::query()->whereRaw('lower(email) = ?', [Str::lower($emails[0])])->first();
        $target = User::query()->whereRaw('lower(email) = ?', [Str::lower($emails[1])])->first();

        if (! $subject instanceof User || ! $target instanceof User) {
            return null;
        }

        return [
            'request_normalization' => $normalized,
            'intent_family' => 'read_explain',
            'capability_key' => 'users.explain.action',
            'filters' => [
                ...$this->emptyFilters(),
                'action_type' => $actionType,
                'target_user_id' => $target->id,
            ],
            'resolved_entity' => [
                'type' => 'user',
                'id' => $subject->id,
                'label' => $subject->name,
            ],
            'missing_slots' => [],
            'clarification_state' => null,
            'proposal_vs_execute' => 'execute',
        ];
    }

    protected function looksLikeActionExplanationPrompt(string $normalized): bool
    {
        return str_contains($normalized, 'por que')
            && $this->extractActionTypeForExplanation($normalized) !== null;
    }

    protected function looksLikeCapabilitiesSummaryPrompt(string $normalized): bool
    {
        return preg_match('/\b(que\s+puede\s+hacer|que\s+no\s+puede\s+hacer)\b/u', $normalized) === 1;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function matchRolesCatalogIntent(string $normalized): ?array
    {
        if (preg_match('/\b(cuales|que)\s+roles\s+(existen|hay)\b/u', $normalized) === 1
            || preg_match('/\b(lista|listar|listame|muestra|mostrar|dame)\s+los\s+roles\b/u', $normalized) === 1) {
            return $this->planFromCapability($normalized, 'users.roles.catalog');
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function matchMixedMetricsSearchIntent(string $normalized): ?array
    {
        if (! $this->looksLikeExplicitCollectionSearch($normalized)) {
            return null;
        }

        if (count($this->metricTopicsFor($normalized)) < 2) {
            return null;
        }

        $searchPlan = $this->searchPlan($this->extractMixedSearchFragment($normalized) ?? $normalized);

        return [
            ...$searchPlan,
            'request_normalization' => $normalized,
            'capability_key' => 'users.mixed.metrics_search',
        ];
    }

    protected function extractMixedSearchFragment(string $normalized): ?string
    {
        $segments = preg_split('/\s+y\s+/u', $normalized) ?: [];

        for ($index = count($segments) - 1; $index >= 0; $index--) {
            $segment = trim((string) ($segments[$index] ?? ''));

            if ($segment === '') {
                continue;
            }

            if ($this->looksLikeExplicitCollectionSearch($segment)
                || preg_match('/\b(listame|que\s+usuarios|quienes\s+son)\b/u', $segment) === 1) {
                return $segment;
            }
        }

        return null;
    }

    protected function looksLikeCombinedMetricsQuery(string $normalized): bool
    {
        if ($this->looksLikeExplicitCollectionSearch($normalized) || $this->looksLikeExplicitDetailPrompt($normalized)) {
            return false;
        }

        return (count($this->metricTopicsFor($normalized)) >= 2
            || ($this->extractAccessProfileFilter($normalized) === 'administrative_access'
                && preg_match('/\b(cantidad|cuantos|cuantas|hay)\b/u', $normalized) === 1
                && preg_match('/\b(quienes\s+son|listame|lista|muestrame|muestra|dime)\b/u', $normalized) === 1))
            && preg_match('/\b(cantidad|cuantos|cuantas|lista|listar|listame|dame|muestra|mostrar)\b/u', $normalized) === 1;
    }

    /**
     * @return list<string>
     */
    protected function metricTopicsFor(string $normalized): array
    {
        $topics = [];

        if (str_contains($normalized, 'cuantos usuarios hay') || str_contains($normalized, 'total actual de usuarios') || str_contains($normalized, 'cantidad de usuarios') || preg_match('/\btotal\b/u', $normalized) === 1) {
            $topics[] = 'total';
        }

        if (str_contains($normalized, 'usuarios activos') || (str_contains($normalized, 'activos') && ! str_contains($normalized, 'inactivos'))) {
            $topics[] = 'active';
        }

        if (str_contains($normalized, 'usuarios inactivos') || str_contains($normalized, 'inactivos')) {
            $topics[] = 'inactive';
        }

        if (str_contains($normalized, 'rol mas comun')) {
            $topics[] = 'most_common_role';
        } elseif (str_contains($normalized, 'distribucion de roles')) {
            $topics[] = 'role_distribution';
        }

        if ($this->extractAccessProfileFilter($normalized) === 'administrative_access'
            && preg_match('/\b(cantidad|cuantos|cuantas|total|tenemos|hay)\b/u', $normalized) === 1) {
            $topics[] = 'admin_access';
        }

        if (str_contains($normalized, 'no estan verificados') || str_contains($normalized, 'no verificados')) {
            $topics[] = 'unverified';
        } elseif (str_contains($normalized, 'estan verificados') || str_contains($normalized, 'verificados')) {
            $topics[] = 'verified';
        }

        if ($this->extractHasRolesFilter($normalized) === false) {
            $topics[] = 'without_roles';
        } elseif ($this->extractHasRolesFilter($normalized) === true) {
            $topics[] = 'with_roles';
        }

        return array_values(array_unique($topics));
    }

    /**
     * @return array<string, mixed>
     */
    protected function combinedMetricsPlan(string $normalized): array
    {
        $roleFilter = $this->extractRoleFilter($normalized);
        $accessProfile = $this->extractAccessProfileFilter($normalized);

        $filters = $this->emptyFilters();

        if ($roleFilter !== null) {
            $filters['role'] = $roleFilter;
        }

        if ($accessProfile !== null) {
            $filters['access_profile'] = $accessProfile;
        }

        return [
            'request_normalization' => $normalized,
            'intent_family' => 'read_metrics',
            'capability_key' => 'users.metrics.combined',
            'filters' => $filters,
            'resolved_entity' => null,
            'missing_slots' => [],
            'clarification_state' => null,
            'proposal_vs_execute' => 'execute',
        ];
    }

    protected function looksLikeSearch(string $normalized): bool
    {
        if ($this->looksLikeExplicitDetailPrompt($normalized) && ! $this->looksLikeExplicitCollectionSearch($normalized)) {
            return false;
        }

        return preg_match('/\b(busca|buscar|muestra|mostrar|lista|listar|listame|encuentra|encontrar|dame)\b/u', $normalized) === 1
            || preg_match('/\b(quienes?|que\s+usuarios)\b.*\b(rol|perfil|permisos|acceso)\b/u', $normalized) === 1
            || preg_match('/\busuarios\s+admin\b/u', $normalized) === 1
            || preg_match('/\badmin\s+del\s+sistema\b/u', $normalized) === 1
            || preg_match('/\busuarios\s+con\s+(rol|perfil)\b/u', $normalized) === 1
            || preg_match('/\badministradores\s+del\s+sistema\b/u', $normalized) === 1
            || preg_match('/\busuarios\s+(con|sin)\s+dos\s+factores\b/u', $normalized) === 1
            || str_contains($normalized, 'usuarios no verificados')
            || str_contains($normalized, 'usuarios sin roles')
            || str_contains($normalized, 'usuarios inactivos');
    }

    /**
     * @return array<string, mixed>
     */
    protected function searchPlan(string $normalized): array
    {
        $filters = $this->emptyFilters();

        if (preg_match('/[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}/', $normalized, $matches) === 1) {
            $filters['query'] = $matches[0];
        }

        if (str_contains($normalized, 'inactivo')) {
            $filters['status'] = 'inactive';
        }

        if (str_contains($normalized, 'activo') && ! str_contains($normalized, 'inactivo')) {
            $filters['status'] = 'active';
        }

        if (str_contains($normalized, 'no verificado')) {
            $filters['email_verified'] = false;
        }

        if (str_contains($normalized, 'verificado') && ! str_contains($normalized, 'no verificado')) {
            $filters['email_verified'] = true;
        }

        if (preg_match('/\b(sin\s+dos\s+factores|sin\s+2fa)\b/u', $normalized) === 1) {
            $filters['two_factor_enabled'] = false;
        }

        if (preg_match('/\b(con\s+dos\s+factores|usuarios\s+con\s+dos\s+factores|con\s+2fa)\b/u', $normalized) === 1) {
            $filters['two_factor_enabled'] = true;
        }

        if ($this->extractHasRolesFilter($normalized) === false) {
            $filters['has_roles'] = false;
        }

        if ($this->extractHasRolesFilter($normalized) === true) {
            $filters['has_roles'] = true;
        }

        $roleFilter = $this->extractRoleFilter($normalized);
        $accessProfile = $this->extractAccessProfileFilter($normalized);

        if ($roleFilter !== null) {
            $filters['role'] = $roleFilter;
        }

        if ($accessProfile !== null) {
            $filters['access_profile'] = $accessProfile;
        }

        if ($filters['access_profile'] === null && preg_match('/\busuarios\s+admin\b/u', $normalized) === 1) {
            $filters['access_profile'] = 'administrative_access';
        }

        $permission = UsersCopilotDomainLexicon::permissionNameForIntent($normalized);

        if ($permission !== null) {
            $filters['permission'] = $permission;
        }

        $filters['query'] ??= $this->extractSearchQuery($normalized);

        return [
            'request_normalization' => $normalized,
            'intent_family' => 'read_search',
            'capability_key' => 'users.search',
            'filters' => $filters,
            'resolved_entity' => null,
            'missing_slots' => [],
            'clarification_state' => null,
            'proposal_vs_execute' => 'execute',
        ];
    }

    protected function extractActionTypeForExplanation(string $normalized): ?string
    {
        return match (true) {
            str_contains($normalized, 'desactiv') => 'deactivate',
            str_contains($normalized, 'activ') || str_contains($normalized, 'reactiv') => 'activate',
            str_contains($normalized, 'reset') || str_contains($normalized, 'restablec') => 'send_reset',
            default => null,
        };
    }

    protected function extractSearchQuery(string $normalized): ?string
    {
        $patterns = [
            '/\b(?:llamad[oa]|nombre)\s+(.+?)(?:\s*$)/u',
            '/\b(?:busca|buscar|encuentra|encontrar|muestra|mostrar|lista|listar|listame|dame)\s+(?:al\s+)?usuario\s+(.+?)(?:\s*$)/u',
            '/\b(?:busca|buscar|encuentra|encontrar)\s+a\s+(.+?)(?:\s*$)/u',
            '/\b(?:con\s+nombre)\s+(.+?)(?:\s*$)/u',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $normalized, $matches) === 1) {
                $query = trim($matches[1]);

                return $query !== '' ? $query : null;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function detailPlan(string $normalized, User $subjectUser): array
    {
        return [
            'request_normalization' => $normalized,
            'intent_family' => 'read_detail',
            'capability_key' => 'users.detail',
            'filters' => [
                ...$this->emptyFilters(),
                'query' => null,
            ],
            'resolved_entity' => [
                'type' => 'user',
                'id' => $subjectUser->id,
                'label' => $subjectUser->name,
            ],
            'missing_slots' => [],
            'clarification_state' => null,
            'proposal_vs_execute' => 'execute',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function permissionExplainPlan(string $normalized, User $subjectUser, string $permission): array
    {
        return [
            'request_normalization' => $normalized,
            'intent_family' => 'read_explain',
            'capability_key' => 'users.explain.permission',
            'filters' => [
                ...$this->emptyFilters(),
                'permission' => $permission,
            ],
            'resolved_entity' => [
                'type' => 'user',
                'id' => $subjectUser->id,
                'label' => $subjectUser->name,
            ],
            'missing_slots' => [],
            'clarification_state' => null,
            'proposal_vs_execute' => 'execute',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function capabilitiesSummaryPlan(string $normalized, User $subjectUser): array
    {
        return [
            'request_normalization' => $normalized,
            'intent_family' => 'read_explain',
            'capability_key' => 'users.explain.capabilities_summary',
            'filters' => $this->emptyFilters(),
            'resolved_entity' => [
                'type' => 'user',
                'id' => $subjectUser->id,
                'label' => $subjectUser->name,
            ],
            'missing_slots' => [],
            'clarification_state' => null,
            'proposal_vs_execute' => 'execute',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function actionPlan(string $normalized, ?User $subjectUser): array
    {
        $capabilityKey = match (true) {
            str_contains($normalized, 'desactiv') => 'users.actions.deactivate',
            str_contains($normalized, 'activ') || str_contains($normalized, 'reactiv') => 'users.actions.activate',
            str_contains($normalized, 'reset'), str_contains($normalized, 'restablec') => 'users.actions.send_reset',
            $this->isCreateUserProposal($normalized) => 'users.actions.create_user',
            default => null,
        };

        if ($capabilityKey === 'users.actions.create_user') {
            return [
                'request_normalization' => $normalized,
                'intent_family' => 'action_proposal',
                'capability_key' => $capabilityKey,
                'filters' => $this->emptyFilters(),
                'resolved_entity' => null,
                'missing_slots' => [],
                'clarification_state' => null,
                'proposal_vs_execute' => 'proposal',
            ];
        }

        if (! $subjectUser instanceof User) {
            return [
                'request_normalization' => $normalized,
                'intent_family' => 'action_proposal',
                'capability_key' => null,
                'filters' => $this->emptyFilters(),
                'resolved_entity' => null,
                'missing_slots' => ['user_id'],
                'clarification_state' => null,
                'proposal_vs_execute' => 'proposal',
            ];
        }

        if ($capabilityKey === null) {
            return $this->helpPlan($normalized);
        }

        return [
            'request_normalization' => $normalized,
            'intent_family' => 'action_proposal',
            'capability_key' => $capabilityKey,
            'filters' => $this->emptyFilters(),
            'resolved_entity' => [
                'type' => 'user',
                'id' => $subjectUser->id,
                'label' => $subjectUser->name,
            ],
            'missing_slots' => [],
            'clarification_state' => null,
            'proposal_vs_execute' => 'proposal',
        ];
    }

    /**
     * @param  list<string>  $missingSlots
     * @return array<string, mixed>
     */
    protected function clarificationPlan(string $normalized, string $reason, string $question, array $missingSlots = []): array
    {
        return [
            'request_normalization' => $normalized,
            'intent_family' => 'ambiguous',
            'capability_key' => 'users.clarification',
            'filters' => $this->emptyFilters(),
            'resolved_entity' => null,
            'missing_slots' => $missingSlots,
            'clarification_state' => [
                'reason' => $reason,
                'question' => $question,
                'options' => [],
            ],
            'proposal_vs_execute' => 'none',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function helpPlan(string $normalized): array
    {
        return [
            'request_normalization' => $normalized,
            'intent_family' => 'help',
            'capability_key' => 'users.help',
            'filters' => $this->emptyFilters(),
            'resolved_entity' => null,
            'missing_slots' => [],
            'clarification_state' => null,
            'proposal_vs_execute' => 'none',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function planFromCapability(string $normalized, string $capabilityKey): array
    {
        $definition = UsersCopilotCapabilityCatalog::find($capabilityKey);

        return [
            'request_normalization' => $normalized,
            'intent_family' => $definition['intent_family'] ?? 'help',
            'capability_key' => $capabilityKey,
            'filters' => $this->emptyFilters(),
            'resolved_entity' => null,
            'missing_slots' => [],
            'clarification_state' => null,
            'proposal_vs_execute' => in_array($definition['family'] ?? null, ['aggregate', 'list', 'detail'], true)
                ? 'execute'
                : 'none',
        ];
    }

    /**
     * @return array{query: ?string, status: 'active'|'inactive'|'all'|null, role: ?string, access_profile: ?string, permission: ?string, email_verified: ?bool, two_factor_enabled: ?bool, has_roles: ?bool}
     */
    protected function emptyFilters(): array
    {
        return [
            'query' => null,
            'status' => null,
            'role' => null,
            'access_profile' => null,
            'permission' => null,
            'email_verified' => null,
            'two_factor_enabled' => null,
            'has_roles' => null,
        ];
    }

    protected function hasSnapshotContext(CopilotConversationSnapshot $snapshot): bool
    {
        return $snapshot->hasContext();
    }

    protected function looksLikeActionProposal(string $normalized): bool
    {
        return preg_match('/\b(propon|prepara|desactiva|desactivalo|activa|reactiva|reactivalo|reset|restablece|restablecele|crea|alta)\b/u', $normalized) === 1
            || (str_contains($normalized, 'restablecimiento') && preg_match('/\b(necesito|quiero|puedo|ayudame|enviar|envia)\b/u', $normalized) === 1);
    }

    protected function looksLikeConversationContinuation(string $normalized): bool
    {
        return preg_match('/\b(continua|continua con|sigue|seguir|amplia|amplia esto|mas detalle|mas detalles)\b/u', $normalized) === 1;
    }

    protected function looksLikeInformationalPrompt(string $normalized): bool
    {
        return preg_match('/\b(como\s+(?:revisar|funciona|puedo|hago|se\s+hace)|explica\s+como|que\s+puedo\s+hacer|que\s+significa)\b/u', $normalized) === 1
            && preg_match('/\b(?:de\s+un\s+usuario|de\s+los\s+usuarios|del\s+usuario|un\s+usuario)\b/u', $normalized) === 1;
    }

    protected function looksLikeCountFollowUp(string $normalized): bool
    {
        return preg_match('/\b(y\s+)?cuantos\s+son\b/u', $normalized) === 1
            || str_contains($normalized, 'de esos cuantos siguen activos')
            || str_contains($normalized, 'cuantos son');
    }

    protected function looksLikeSubsetRefinement(string $normalized): bool
    {
        return str_contains($normalized, 'solo los ')
            || str_contains($normalized, 'ahora solo los ')
            || str_contains($normalized, 'muestrame los ')
            || str_contains($normalized, 'solo las ');
    }

    protected function looksLikeEntityReference(string $normalized): bool
    {
        return preg_match('/\b(ese usuario|esa usuaria|desactivalo|reactivalo|restablecele|propon desactivarlo|propon activarlo)\b/u', $normalized) === 1;
    }

    protected function looksLikeUserTargetingPrompt(string $normalized): bool
    {
        if ($this->looksLikeActionProposal($normalized)) {
            return true;
        }

        if ($this->mentionsUserAbstractly($normalized)) {
            return false;
        }

        return preg_match('/\b(revisa|resume|explica|detalle|estado|acceso|usuario|permisos|roles|puede)\b/u', $normalized) === 1;
    }

    protected function looksLikeExplicitDetailPrompt(string $normalized): bool
    {
        if ($this->extractEmailFromText($normalized) !== null
            && preg_match('/\b(que\s+permisos\s+tiene|que\s+roles?\s+tiene|que\s+puede\s+hacer|que\s+rol(?:es)?\s+tiene)\b/u', $normalized) === 1) {
            return true;
        }

        if (preg_match('/\b(que\s+permisos\s+tiene|que\s+rol(?:es)?\s+tiene|que\s+rol\s+tiene|roles\s+y\s+permisos|permisos\s+y\s+rol|acceso\s+efectivo|estado\s+actual|estado\s+operativo)\b/u', $normalized) === 1) {
            return true;
        }

        if ($this->extractEmailFromText($normalized) !== null
            && preg_match('/\b(usuario|usuaria|revisa|resume|explica|detalle|estado|acceso|permisos|rol|roles)\b/u', $normalized) === 1) {
            return true;
        }

        return preg_match('/\b(revisa|resume|explica|detalle|estado|acceso|permisos|roles|puede|por\s+que)\b/u', $normalized) === 1
            && (preg_match('/\busuario\b/u', $normalized) === 1 || $this->extractEmailFromText($normalized) !== null);
    }

    protected function looksLikeExplicitCollectionSearch(string $normalized): bool
    {
        return preg_match('/\b(busca|buscar|lista|listar|listame|muestra|mostrar|encuentra|encontrar|dame)\b.*\busuarios?\b/u', $normalized) === 1
            || preg_match('/\b(que\s+usuarios|quienes\s+son\s+los\s+usuarios|quienes\s+son)\b/u', $normalized) === 1
            || preg_match('/\b(quienes?|que\s+usuarios)\b.*\b(rol|perfil|permisos|acceso)\b/u', $normalized) === 1;
    }

    protected function mentionsUserAbstractly(string $normalized): bool
    {
        if ($this->extractEmailFromText($normalized) !== null) {
            return false;
        }

        if (preg_match('/\b(?:un\s+usuario|de\s+un\s+usuario|los\s+usuarios|de\s+los\s+usuarios|del\s+sistema)\b/u', $normalized) !== 1) {
            return false;
        }

        return preg_match('/\b(?:al\s+usuario\s+\w{2,}|usuario\s+[a-z]{2,}\s+[a-z])\b/u', $normalized) !== 1;
    }

    protected function extractEntitySearchQuery(string $normalized): ?string
    {
        $email = $this->extractEmailFromText($normalized);

        if ($email !== null) {
            return $email;
        }

        $patterns = [
            '/\b(?:explica|explicame|revisa|resume)\s+(?:el|la)?\s*(?:acceso\s+efectivo|estado\s+actual|estado\s+operativo|detalle(?:s)?|resumen)\s+de\s+(.+)$/u',
            '/\b(?:usuario|usuaria|a)\s+(.+)$/u',
            '/\b(?:revisa|resume|explica|detalle|estado|acceso)\s+(?:de\s+)?(.+)$/u',
            '/\b(?:desactiva|activa|reactiva|restablece|propon\s+desactivar\s+a|propon\s+activar\s+a|enviar\s+un\s+restablecimiento\s+a|envia\s+un\s+restablecimiento\s+a)\s+(.+)$/u',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $normalized, $matches) === 1) {
                return $this->sanitizeEntityCandidate($matches[1]);
            }
        }

        return null;
    }

    /**
     * @return Collection<int, User>
     */
    protected function candidateUsersFor(string $query): Collection
    {
        $normalized = $this->normalizePrompt($query);
        $email = $this->extractEmailFromText($normalized);

        if ($email !== null) {
            return User::query()
                ->whereRaw('lower(email) = ?', [$email])
                ->orderBy('name')
                ->limit(5)
                ->get();
        }

        $users = User::query()->select(['id', 'name', 'email'])->get();

        $exactNameMatches = $users
            ->filter(fn (User $user): bool => $this->normalizePrompt($user->name) === $normalized)
            ->values();

        if ($exactNameMatches->isNotEmpty()) {
            return $exactNameMatches->take(5)->values();
        }

        $tokens = $this->searchTokens($normalized);

        return $users
            ->filter(function (User $user) use ($normalized, $tokens): bool {
                $normalizedName = $this->normalizePrompt($user->name);
                $normalizedEmail = $this->normalizePrompt($user->email);

                if ($normalized !== '' && (str_contains($normalizedName, $normalized) || str_contains($normalizedEmail, $normalized))) {
                    return true;
                }

                if (count($tokens) < 2) {
                    return false;
                }

                foreach ($tokens as $token) {
                    if (! str_contains($normalizedName, $token)) {
                        return false;
                    }
                }

                return true;
            })
            ->sortBy(fn (User $user): array => [
                str_starts_with($this->normalizePrompt($user->name), $normalized) ? 0 : 1,
                abs(mb_strlen($this->normalizePrompt($user->name)) - mb_strlen($normalized)),
                $this->normalizePrompt($user->name),
            ])
            ->take(5)
            ->values();
    }

    /**
     * @param  Collection<int, User>  $matches
     * @return array<string, mixed>
     */
    protected function ambiguousUserClarificationPlan(string $normalized, Collection $matches, string $capabilityKey): array
    {
        $forAction = $capabilityKey !== 'users.detail';
        $question = $forAction
            ? 'Encontre varios usuarios posibles. Indica cual quieres usar para la propuesta.'
            : 'Encontre varios usuarios posibles. Indica cual quieres revisar.';

        return [
            'request_normalization' => $normalized,
            'intent_family' => 'ambiguous',
            'capability_key' => 'users.clarification',
            'filters' => $this->emptyFilters(),
            'resolved_entity' => null,
            'missing_slots' => ['user_id'],
            'clarification_state' => [
                'reason' => 'ambiguous_target',
                'question' => $question,
                'options' => $matches->map(fn (User $user): array => [
                    'label' => sprintf('%s <%s>', $user->name, $user->email),
                    'value' => 'user:'.$user->id,
                    'capability_key' => $capabilityKey,
                    'intent_family' => $forAction ? 'action_proposal' : 'read_detail',
                    'resolved_entity' => [
                        'type' => 'user',
                        'id' => $user->id,
                        'label' => $user->name,
                    ],
                ])->values()->all(),
            ],
            'proposal_vs_execute' => 'none',
        ];
    }

    protected function actionCapabilityKey(string $normalized): string
    {
        return match (true) {
            str_contains($normalized, 'desactiv') => 'users.actions.deactivate',
            str_contains($normalized, 'activ') || str_contains($normalized, 'reactiv') => 'users.actions.activate',
            str_contains($normalized, 'reset'), str_contains($normalized, 'restablec') => 'users.actions.send_reset',
            $this->isCreateUserProposal($normalized) => 'users.actions.create_user',
            default => 'users.actions.deactivate',
        };
    }

    protected function isCreateUserProposal(string $normalized): bool
    {
        return str_contains($normalized, 'crear usuario') || str_contains($normalized, 'alta');
    }

    protected function extractEmailFromText(string $text): ?string
    {
        if (preg_match('/[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}/', $text, $matches) !== 1) {
            return null;
        }

        return Str::lower($matches[0]);
    }

    protected function sanitizeEntityCandidate(string $candidate): ?string
    {
        $sanitized = $this->normalizePrompt($candidate);
        $sanitized = preg_replace('/^(?:el|la)?\s*usuario\s+(?:es|se\s+llama)?\s*/u', '', $sanitized) ?? $sanitized;
        $sanitized = preg_replace('/^(?:al|a\s+la|a\s+el|del|de\s+la)\s+/u', '', $sanitized) ?? $sanitized;
        $sanitized = preg_replace('/^(?:efectivo|actual|operativo)\s+de\s+/u', '', $sanitized) ?? $sanitized;
        $sanitized = preg_replace('/^(?:el|la|los|las)\s+(?:acceso\s+efectivo|estado\s+actual|estado\s+operativo|detalle(?:s)?|resumen)\s+de\s+/u', '', $sanitized) ?? $sanitized;
        $sanitized = preg_replace('/^(?:el|la|los|las)\s+(?:acceso\s+efectivo|estado\s+actual|estado\s+operativo|detalle(?:s)?|resumen)\s+/u', '', $sanitized) ?? $sanitized;
        $sanitized = preg_replace('/\b(?:que\s+permisos\s+tiene.*|que\s+rol(?:es)?\s+tiene.*|y\s+que\s+rol.*|y\s+que\s+permisos.*|con\s+que\s+rol.*|cuales?\s+son\s+sus\s+roles.*|cuales?\s+son\s+sus\s+permisos.*)$/u', '', $sanitized) ?? $sanitized;
        $sanitized = trim($sanitized, " .,!?:;\"'");

        return $sanitized !== '' ? $sanitized : null;
    }

    protected function extractRoleFilter(string $normalized): ?string
    {
        $patterns = [
            '/\bque\s+usuarios\s+(?:son|sean|tienen\s+(?:el\s+)?(?:rol|perfil)\s+(?:de\s+)?)\s*(.+)$/u',
            '/\busuarios\s+(?:son|sean|con\s+rol|con\s+perfil)\s+(.+)$/u',
            '/\b(?:rol|perfil)\s+(.+)$/u',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $normalized, $matches) === 1) {
                $role = trim((string) ($matches[1] ?? ''));
                $role = trim((preg_replace('/\s+y\s+.*$/u', '', $role) ?? $role));
                $role = trim((preg_replace('/\b(?:activos?|inactivos?|usuarios?|usuario|permisos?|acceso|efectivo)\b/u', ' ', $role) ?? $role));
                $role = UsersCopilotDomainLexicon::canonicalRole($role);

                if ($role !== '') {
                    return $role;
                }
            }
        }

        return null;
    }

    protected function extractAccessProfileFilter(string $normalized): ?string
    {
        if (preg_match('/\b(rol|perfil)\s+super-admin\b/u', $normalized) === 1) {
            return 'super_admin_role';
        }

        if (preg_match('/\bsuper-admin\b/u', $normalized) === 1 && preg_match('/\b(permisos|acceso|usuarios?|lista|lista(me)?|quienes?)\b/u', $normalized) === 1) {
            return 'super_admin_role';
        }

        return UsersCopilotDomainLexicon::accessProfile($normalized);
    }

    protected function clarificationHint(string $normalized): ?string
    {
        return $this->sanitizeEntityCandidate($this->extractEntitySearchQuery($normalized) ?? $normalized);
    }

    /**
     * @param  array<string, mixed>  $option
     */
    protected function clarificationOptionMatchesPrompt(array $option, string $candidate): bool
    {
        $candidate = $this->normalizePrompt($candidate);

        if ($candidate === '') {
            return false;
        }

        $label = $this->normalizePrompt((string) ($option['label'] ?? ''));
        $value = $this->normalizePrompt((string) ($option['value'] ?? ''));
        $resolvedLabel = $this->normalizePrompt((string) data_get($option, 'resolved_entity.label', ''));
        $optionEmail = $this->extractEmailFromText($label);
        $candidateEmail = $this->extractEmailFromText($candidate);

        if ($candidateEmail !== null && $optionEmail !== null) {
            return $candidateEmail === $optionEmail;
        }

        return $candidate === $label
            || $candidate === $value
            || $candidate === $resolvedLabel
            || str_contains($label, $candidate)
            || ($resolvedLabel !== '' && str_contains($resolvedLabel, $candidate));
    }

    /**
     * @param  list<array<string, mixed>>  $options
     * @return array<string, mixed>|null
     */
    protected function resolveClarificationOptionFromUserHint(string $hint, array $options): ?array
    {
        $matches = $this->candidateUsersFor($hint);

        if ($matches->count() !== 1) {
            return null;
        }

        $userId = $matches->first()?->id;

        if (! is_int($userId)) {
            return null;
        }

        return collect($options)->first(fn (array $option): bool => (int) data_get($option, 'resolved_entity.id', 0) === $userId);
    }

    /**
     * @return list<string>
     */
    protected function searchTokens(string $normalized): array
    {
        return array_values(array_filter(
            preg_split('/\s+/', $normalized) ?: [],
            static fn (string $token): bool => mb_strlen($token) >= 3 && ! in_array($token, ['el', 'la', 'los', 'las', 'del', 'de', 'usuario', 'usuaria'], true),
        ));
    }

    protected function extractHasRolesFilter(string $normalized): ?bool
    {
        if (str_contains($normalized, 'sin roles')) {
            return false;
        }

        if (str_contains($normalized, 'con roles') || str_contains($normalized, 'tienen roles')) {
            return true;
        }

        return null;
    }
}
