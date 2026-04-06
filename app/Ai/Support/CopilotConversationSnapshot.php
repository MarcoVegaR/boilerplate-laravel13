<?php

namespace App\Ai\Support;

use Illuminate\Contracts\Support\Arrayable;
use JsonException;
use JsonSerializable;

class CopilotConversationSnapshot implements Arrayable, JsonSerializable
{
    public const VERSION = 1;

    public const RESULT_USER_ID_LIMIT = 8;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(protected array $attributes = [])
    {
        $this->attributes = self::normalizeAttributes($attributes);
    }

    public static function empty(): self
    {
        return new self;
    }

    public static function fromDatabase(mixed $snapshot, mixed $version = null): self
    {
        $attributes = [];

        if (is_array($snapshot)) {
            $attributes = $snapshot;
        }

        if (is_string($snapshot) && trim($snapshot) !== '') {
            try {
                $decoded = json_decode($snapshot, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                $decoded = [];
            }

            if (is_array($decoded)) {
                $attributes = $decoded;
            }
        }

        $attributes['conversation_state_version'] = is_numeric($version)
            ? (int) $version
            : ($attributes['conversation_state_version'] ?? self::VERSION);

        return new self($attributes);
    }

    public function version(): int
    {
        return (int) $this->attributes['conversation_state_version'];
    }

    public function lastCapabilityKey(): ?string
    {
        return $this->attributes['last_capability_key'];
    }

    /**
     * @return array<string, mixed>
     */
    public function lastFilters(): array
    {
        return $this->attributes['last_filters'];
    }

    /**
     * @return list<int>
     */
    public function lastResultUserIds(): array
    {
        return $this->attributes['last_result_user_ids'];
    }

    public function lastResultCount(): ?int
    {
        return $this->attributes['last_result_count'];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function lastMetricsSnapshot(): ?array
    {
        return $this->attributes['last_metrics_snapshot'];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function pendingClarification(): ?array
    {
        return $this->attributes['pending_clarification'];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function pendingActionProposal(): ?array
    {
        return $this->attributes['pending_action_proposal'];
    }

    /**
     * @return array{type: string, id: int}|null
     */
    public function resolvedEntity(): ?array
    {
        if (! is_string($this->attributes['last_resolved_entity_type']) || ! is_int($this->attributes['last_resolved_entity_id'])) {
            return null;
        }

        return [
            'type' => $this->attributes['last_resolved_entity_type'],
            'id' => $this->attributes['last_resolved_entity_id'],
        ];
    }

    public function hasContext(): bool
    {
        return $this->lastCapabilityKey() !== null
            || $this->lastResultCount() !== null
            || $this->pendingClarification() !== null
            || $this->pendingActionProposal() !== null
            || $this->resolvedEntity() !== null;
    }

    public function singleResultUserId(): ?int
    {
        $userIds = $this->lastResultUserIds();

        return count($userIds) === 1 ? $userIds[0] : null;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function with(array $attributes): self
    {
        return new self([
            ...$this->attributes,
            ...$attributes,
        ]);
    }

    /**
     * @return array{snapshot: string, snapshot_version: int}
     */
    public function toDatabase(): array
    {
        return [
            'snapshot' => json_encode($this->jsonSerialize(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            'snapshot_version' => $this->version(),
        ];
    }

    /**
     * @return array{
     *   last_user_request_normalized: ?string,
     *   last_intent_family: ?string,
     *   last_capability_key: ?string,
     *   last_filters: array<string, mixed>,
     *   last_resolved_entity_type: ?string,
     *   last_resolved_entity_id: ?int,
     *   last_result_user_ids: list<int>,
     *   last_result_count: ?int,
     *   last_metrics_snapshot: array<string, mixed>|null,
     *   pending_clarification: array<string, mixed>|null,
     *   pending_action_proposal: array<string, mixed>|null,
     *   conversation_state_version: int
     * }
     */
    public function toArray(): array
    {
        return $this->attributes;
    }

    /**
     * @return array{
     *   last_user_request_normalized: ?string,
     *   last_intent_family: ?string,
     *   last_capability_key: ?string,
     *   last_filters: array<string, mixed>,
     *   last_resolved_entity_type: ?string,
     *   last_resolved_entity_id: ?int,
     *   last_result_user_ids: list<int>,
     *   last_result_count: ?int,
     *   last_metrics_snapshot: array<string, mixed>|null,
     *   pending_clarification: array<string, mixed>|null,
     *   pending_action_proposal: array<string, mixed>|null,
     *   conversation_state_version: int
     * }
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{
     *   last_user_request_normalized: ?string,
     *   last_intent_family: ?string,
     *   last_capability_key: ?string,
     *   last_filters: array<string, mixed>,
     *   last_resolved_entity_type: ?string,
     *   last_resolved_entity_id: ?int,
     *   last_result_user_ids: list<int>,
     *   last_result_count: ?int,
     *   last_metrics_snapshot: array<string, mixed>|null,
     *   pending_clarification: array<string, mixed>|null,
     *   pending_action_proposal: array<string, mixed>|null,
     *   conversation_state_version: int
     * }
     */
    protected static function normalizeAttributes(array $attributes): array
    {
        return [
            'last_user_request_normalized' => is_string($attributes['last_user_request_normalized'] ?? null)
                ? $attributes['last_user_request_normalized']
                : null,
            'last_intent_family' => is_string($attributes['last_intent_family'] ?? null)
                ? $attributes['last_intent_family']
                : null,
            'last_capability_key' => is_string($attributes['last_capability_key'] ?? null)
                ? $attributes['last_capability_key']
                : null,
            'last_filters' => is_array($attributes['last_filters'] ?? null)
                ? $attributes['last_filters']
                : [],
            'last_resolved_entity_type' => is_string($attributes['last_resolved_entity_type'] ?? null)
                ? $attributes['last_resolved_entity_type']
                : null,
            'last_resolved_entity_id' => is_numeric($attributes['last_resolved_entity_id'] ?? null)
                ? (int) $attributes['last_resolved_entity_id']
                : null,
            'last_result_user_ids' => array_values(array_map(
                static fn (int|string $userId): int => (int) $userId,
                array_slice(array_filter(
                    is_array($attributes['last_result_user_ids'] ?? null) ? $attributes['last_result_user_ids'] : [],
                    static fn (mixed $userId): bool => is_int($userId) || (is_string($userId) && is_numeric($userId)),
                ), 0, self::RESULT_USER_ID_LIMIT),
            )),
            'last_result_count' => is_numeric($attributes['last_result_count'] ?? null)
                ? (int) $attributes['last_result_count']
                : null,
            'last_metrics_snapshot' => is_array($attributes['last_metrics_snapshot'] ?? null)
                ? $attributes['last_metrics_snapshot']
                : null,
            'pending_clarification' => is_array($attributes['pending_clarification'] ?? null)
                ? $attributes['pending_clarification']
                : null,
            'pending_action_proposal' => is_array($attributes['pending_action_proposal'] ?? null)
                ? $attributes['pending_action_proposal']
                : null,
            'conversation_state_version' => is_numeric($attributes['conversation_state_version'] ?? null)
                ? max((int) $attributes['conversation_state_version'], 1)
                : self::VERSION,
        ];
    }
}
