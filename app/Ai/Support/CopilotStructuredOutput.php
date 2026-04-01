<?php

namespace App\Ai\Support;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Arr;
use JsonException;
use Laravel\Ai\Responses\AgentResponse;

class CopilotStructuredOutput
{
    public const PROFILE_DEFAULT = 'default';

    public const PROFILE_GEMINI = 'gemini';

    /**
     * Get the structured schema shared by copilot agents.
     *
     * @return array<string, Type>
     */
    public static function schema(JsonSchema $schema, string $profile = self::PROFILE_DEFAULT): array
    {
        if ($profile === self::PROFILE_GEMINI) {
            return self::geminiSchema($schema);
        }

        return [
            'answer' => $schema->string()->required(),
            'intent' => $schema->string()->enum([
                'help',
                'inform',
                'search_results',
                'user_context',
                'action_proposal',
                'out_of_scope',
                'error',
            ])->required(),
            'cards' => $schema->array()->items(
                $schema->object([
                    'kind' => $schema->string()->enum(['notice', 'search_results', 'user_context'])->required(),
                    'title' => $schema->string()->nullable(),
                    'summary' => $schema->string()->nullable(),
                    'data' => $schema->object()->required(),
                ])->withoutAdditionalProperties()
            )->default([]),
            'actions' => $schema->array()->items(
                $schema->object([
                    'kind' => $schema->string()->enum(['action_proposal'])->required(),
                    'action_type' => $schema->string()->enum(array_column(CopilotActionType::cases(), 'value'))->required(),
                    'target' => $schema->object()->required(),
                    'summary' => $schema->string()->required(),
                    'payload' => $schema->object()->required(),
                    'can_execute' => $schema->boolean()->required(),
                    'deny_reason' => $schema->string()->nullable(),
                    'required_permissions' => $schema->array()->items(
                        $schema->string()
                    )->default([]),
                ])->withoutAdditionalProperties()
            )->default([]),
            'requires_confirmation' => $schema->boolean()->required(),
            'references' => $schema->array()->items(
                $schema->object([
                    'label' => $schema->string()->required(),
                    'href' => $schema->string()->nullable(),
                ])->withoutAdditionalProperties()
            )->default([]),
            'meta' => $schema->object([
                'module' => $schema->string()->required(),
                'channel' => $schema->string()->required(),
                'subject_user_id' => $schema->integer()->nullable(),
                'fallback' => $schema->boolean()->required(),
                'diagnostics' => $schema->object()->nullable(),
            ])->withoutAdditionalProperties()->required(),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    public static function normalize(array $payload, string $profile = self::PROFILE_DEFAULT): ?array
    {
        $requiredKeys = ['answer', 'intent', 'cards', 'actions', 'requires_confirmation', 'references', 'meta'];

        foreach ($requiredKeys as $requiredKey) {
            if (! array_key_exists($requiredKey, $payload)) {
                return null;
            }
        }

        if ($profile !== self::PROFILE_GEMINI) {
            return $payload;
        }

        $cards = [];

        foreach (Arr::wrap($payload['cards']) as $card) {
            if (! is_array($card)) {
                return null;
            }

            $data = $card['data'] ?? self::decodeJsonObject($card['data_json'] ?? null);

            if (! is_array($data)) {
                return null;
            }

            $cards[] = [
                'kind' => $card['kind'] ?? null,
                'title' => $card['title'] ?? null,
                'summary' => $card['summary'] ?? null,
                'data' => $data,
            ];
        }

        $actions = [];

        foreach (Arr::wrap($payload['actions']) as $action) {
            if (! is_array($action)) {
                return null;
            }

            $target = $action['target'] ?? self::decodeJsonObject($action['target_json'] ?? null);
            $actionPayload = $action['payload'] ?? self::decodeJsonObject($action['payload_json'] ?? null);

            if (! is_array($target) || ! is_array($actionPayload)) {
                return null;
            }

            $actions[] = [
                'kind' => $action['kind'] ?? null,
                'action_type' => $action['action_type'] ?? null,
                'target' => $target,
                'summary' => $action['summary'] ?? null,
                'payload' => $actionPayload,
                'can_execute' => (bool) ($action['can_execute'] ?? false),
                'deny_reason' => $action['deny_reason'] ?? null,
                'required_permissions' => array_values(array_filter(
                    Arr::wrap($action['required_permissions'] ?? []),
                    static fn (mixed $permission): bool => is_string($permission) && $permission !== '',
                )),
            ];
        }

        $meta = is_array($payload['meta']) ? $payload['meta'] : null;

        if ($meta === null) {
            return null;
        }

        $diagnostics = $meta['diagnostics'] ?? self::decodeJsonObject($meta['diagnostics_json'] ?? null);

        if ($diagnostics !== null && ! is_array($diagnostics)) {
            return null;
        }

        return [
            'answer' => $payload['answer'],
            'intent' => $payload['intent'],
            'cards' => $cards,
            'actions' => $actions,
            'requires_confirmation' => (bool) $payload['requires_confirmation'],
            'references' => array_values(array_filter(
                Arr::wrap($payload['references']),
                static fn (mixed $reference): bool => is_array($reference),
            )),
            'meta' => [
                'module' => $meta['module'] ?? 'users',
                'channel' => $meta['channel'] ?? 'web',
                'subject_user_id' => $meta['subject_user_id'] ?? null,
                'fallback' => (bool) ($meta['fallback'] ?? false),
                'diagnostics' => $diagnostics,
            ],
        ];
    }

    public static function profileForProvider(string|array|null $provider): string
    {
        return CopilotProviderProfile::forProvider($provider)->schemaProfile;
    }

    /**
     * @return array<string, mixed>
     */
    public static function normalizeResponse(AgentResponse $response, CopilotProviderProfile $profile, ?int $subjectUserId): array
    {
        $payload = self::extractPayloadFromResponse($response, $profile);

        if (! is_array($payload)) {
            return self::fallback(
                diagnostics: ['reason' => 'missing_structured_output'],
                subjectUserId: $subjectUserId,
            );
        }

        $normalized = self::normalize($payload, $profile->schemaProfile);

        if ($normalized === null) {
            return self::fallback(
                diagnostics: ['reason' => 'invalid_structured_output'],
                subjectUserId: $subjectUserId,
            );
        }

        return $normalized;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function extractPayloadFromResponse(AgentResponse $response, CopilotProviderProfile $profile): ?array
    {
        if ($response instanceof Arrayable) {
            $payload = $response->toArray();

            return is_array($payload) ? $payload : null;
        }

        if ($profile->usesTextJsonResponses()) {
            return self::decodeTextPayload($response->text);
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function decodeTextPayload(?string $text): ?array
    {
        if ($text === null || trim($text) === '') {
            return null;
        }

        $text = trim($text);

        if (preg_match('/```(?:json)?\s*(.*?)\s*```/is', $text, $matches) === 1) {
            $text = trim($matches[1]);
        }

        try {
            $decoded = json_decode($text, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Build a safe fallback response for malformed or unavailable output paths.
     *
     * @param  array<string, mixed>  $diagnostics
     * @return array<string, mixed>
     */
    public static function fallback(
        ?string $answer = null,
        array $diagnostics = [],
        ?int $subjectUserId = null,
    ): array {
        return [
            'answer' => $answer ?? config('ai-copilot.fallback.message'),
            'intent' => 'error',
            'cards' => [],
            'actions' => [],
            'requires_confirmation' => false,
            'references' => [],
            'meta' => [
                'module' => 'users',
                'channel' => 'web',
                'subject_user_id' => $subjectUserId,
                'fallback' => true,
                'diagnostics' => empty($diagnostics) ? null : $diagnostics,
            ],
        ];
    }

    /**
     * @return array<string, Type>
     */
    protected static function geminiSchema(JsonSchema $schema): array
    {
        return [
            'answer' => $schema->string()->required(),
            'intent' => $schema->string()->enum([
                'help',
                'inform',
                'search_results',
                'user_context',
                'action_proposal',
                'out_of_scope',
                'error',
            ])->required(),
            'cards' => $schema->array()->items(
                $schema->object([
                    'kind' => $schema->string()->enum(['notice', 'search_results', 'user_context'])->required(),
                    'title' => $schema->string()->nullable(),
                    'summary' => $schema->string()->nullable(),
                    'data_json' => $schema->string()->required(),
                ])->withoutAdditionalProperties()
            )->default([]),
            'actions' => $schema->array()->items(
                $schema->object([
                    'kind' => $schema->string()->enum(['action_proposal'])->required(),
                    'action_type' => $schema->string()->enum(array_column(CopilotActionType::cases(), 'value'))->required(),
                    'target_json' => $schema->string()->required(),
                    'summary' => $schema->string()->required(),
                    'payload_json' => $schema->string()->required(),
                    'can_execute' => $schema->boolean()->required(),
                    'deny_reason' => $schema->string()->nullable(),
                    'required_permissions' => $schema->array()->items(
                        $schema->string()
                    )->default([]),
                ])->withoutAdditionalProperties()
            )->default([]),
            'requires_confirmation' => $schema->boolean()->required(),
            'references' => $schema->array()->items(
                $schema->object([
                    'label' => $schema->string()->required(),
                    'href' => $schema->string()->nullable(),
                ])->withoutAdditionalProperties()
            )->default([]),
            'meta' => $schema->object([
                'module' => $schema->string()->required(),
                'channel' => $schema->string()->required(),
                'subject_user_id' => $schema->integer()->nullable(),
                'fallback' => $schema->boolean()->required(),
                'diagnostics_json' => $schema->string()->nullable(),
            ])->withoutAdditionalProperties()->required(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected static function decodeJsonObject(mixed $value): ?array
    {
        if ($value === null) {
            return null;
        }

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }
}
