<?php

namespace App\Ai\Services;

use App\Ai\Agents\System\UsersCopilotAgent;
use App\Ai\Agents\System\UsersGeminiCopilotAgent;
use App\Ai\Support\BaseCopilotAgent;
use App\Ai\Support\CopilotProviderProfile;
use App\Ai\Support\CopilotStructuredOutput;
use App\Ai\Testing\BrowserCopilotFakeTransport;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Ai\Responses\AgentResponse;
use Throwable;

class CopilotConversationService
{
    public function __construct(
        protected DatabaseManager $database,
        protected BrowserCopilotFakeTransport $browserFakeTransport,
        protected UsersGeminiCapabilityOrchestrator $usersGeminiCapabilityOrchestrator,
    ) {}

    /**
     * @return array{conversation_id: string, response: array<string, mixed>}
     */
    public function respond(
        User $actor,
        string $prompt,
        ?string $conversationId = null,
        ?int $subjectUserId = null,
    ): array {
        $subjectUser = $subjectUserId !== null ? User::query()->findOrFail($subjectUserId) : null;

        if ($subjectUser !== null && ! $actor->can('view', $subjectUser)) {
            throw new AuthorizationException(__('No puedes consultar este usuario desde el copiloto.'));
        }

        $agent = $this->makeAgent($actor, $subjectUser);
        $isNewConversation = $conversationId === null;
        $conversationId ??= $this->resolveConversationId($actor, $prompt, $agent);

        $this->assertConversationOwnership($conversationId, $actor);

        try {
            if ($payload = $this->resolveBrowserFakeResponse($subjectUser?->id)) {
                $this->storeUserMessage($conversationId, $actor, $prompt, $agent);
                $this->storeAssistantPayloadMessage($conversationId, $actor, $payload, $agent, 'browser_file');

                return [
                    'conversation_id' => $conversationId,
                    'response' => $payload,
                ];
            }

            if ($agent instanceof UsersGeminiCopilotAgent) {
                return $this->respondWithGeminiOrchestrator($agent, $actor, $prompt, $conversationId, $subjectUser?->id);
            }

            $response = $agent
                ->continue($conversationId, as: $actor)
                ->prompt($prompt);

            $payload = $this->normalizeResponse($response, $subjectUser?->id);

            $this->storeUserMessage($conversationId, $actor, $prompt, $agent);
            $this->storeAssistantMessage($conversationId, $actor, $response, $agent);

            return [
                'conversation_id' => $conversationId,
                'response' => $payload,
            ];
        } catch (AuthorizationException|ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            if ($isNewConversation) {
                $this->touchConversation($conversationId);
            }

            return [
                'conversation_id' => $conversationId,
                'response' => CopilotStructuredOutput::fallback(
                    diagnostics: [
                        'reason' => 'agent_unavailable',
                        'exception' => class_basename($exception),
                    ],
                    subjectUserId: $subjectUser?->id,
                ),
            ];
        }
    }

    /**
     * @return array{conversation_id: string, response: array<string, mixed>}
     */
    protected function respondWithGeminiOrchestrator(
        UsersGeminiCopilotAgent $agent,
        User $actor,
        string $prompt,
        string $conversationId,
        ?int $subjectUserId,
    ): array {
        $orchestration = $this->usersGeminiCapabilityOrchestrator->prepare($agent, $prompt);
        $diagnostics = [];

        try {
            $response = $agent
                ->continue($conversationId, as: $actor)
                ->prompt($orchestration['prompt']);

            $normalized = $this->normalizeResponse($response, $subjectUserId);

            if (($normalized['meta']['fallback'] ?? false) === true) {
                $diagnostics['formatter_reason'] = Arr::get($normalized, 'meta.diagnostics.reason', 'missing_structured_output');
                $normalized = null;
            } else {
                $diagnostics['formatter_result'] = 'gemini_text_json';
            }
        } catch (AuthorizationException|ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            $normalized = null;
            $diagnostics['formatter_exception'] = class_basename($exception);
        }

        $payload = $this->usersGeminiCapabilityOrchestrator->finalizePayload(
            $orchestration['payload'],
            $normalized,
            $diagnostics,
        );

        $this->storeUserMessage($conversationId, $actor, $prompt, $agent);
        $this->storeAssistantPayloadMessage($conversationId, $actor, $payload, $agent, 'gemini_local_orchestrator');

        return [
            'conversation_id' => $conversationId,
            'response' => $payload,
        ];
    }

    protected function resolveConversationId(User $actor, string $prompt, BaseCopilotAgent $agent): string
    {
        $conversationId = (string) Str::uuid7();

        $this->database->table('agent_conversations')->insert([
            'id' => $conversationId,
            'user_id' => $actor->id,
            'title' => $agent->conversationTitleFor($prompt),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $conversationId;
    }

    protected function assertConversationOwnership(string $conversationId, User $actor): void
    {
        $conversation = $this->database->table('agent_conversations')->where('id', $conversationId)->first();

        if ($conversation === null) {
            throw ValidationException::withMessages([
                'conversation_id' => __('La conversacion indicada no existe.'),
            ]);
        }

        if ((int) $conversation->user_id !== $actor->id) {
            throw new AuthorizationException(__('No puedes continuar una conversacion que no te pertenece.'));
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function normalizeResponse(AgentResponse $response, ?int $subjectUserId): array
    {
        return CopilotStructuredOutput::normalizeResponse(
            $response,
            CopilotProviderProfile::forProvider($response->meta->provider),
            $subjectUserId,
        );
    }

    protected function storeUserMessage(string $conversationId, User $actor, string $prompt, BaseCopilotAgent $agent): void
    {
        $this->database->table('agent_conversation_messages')->insert([
            'id' => (string) Str::uuid7(),
            'conversation_id' => $conversationId,
            'user_id' => $actor->id,
            'agent' => $agent::class,
            'role' => 'user',
            'content' => $prompt,
            'attachments' => '[]',
            'tool_calls' => '[]',
            'tool_results' => '[]',
            'usage' => '[]',
            'meta' => '[]',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->touchConversation($conversationId);
    }

    protected function storeAssistantMessage(string $conversationId, User $actor, AgentResponse $response, BaseCopilotAgent $agent): void
    {
        $this->database->table('agent_conversation_messages')->insert([
            'id' => (string) Str::uuid7(),
            'conversation_id' => $conversationId,
            'user_id' => $actor->id,
            'agent' => $agent::class,
            'role' => 'assistant',
            'content' => $response->text,
            'attachments' => '[]',
            'tool_calls' => json_encode($response->toolCalls),
            'tool_results' => json_encode($response->toolResults),
            'usage' => json_encode($response->usage),
            'meta' => json_encode($response->meta),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->touchConversation($conversationId);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function storeAssistantPayloadMessage(
        string $conversationId,
        User $actor,
        array $payload,
        BaseCopilotAgent $agent,
        ?string $payloadSource = null,
    ): void {
        $this->database->table('agent_conversation_messages')->insert([
            'id' => (string) Str::uuid7(),
            'conversation_id' => $conversationId,
            'user_id' => $actor->id,
            'agent' => $agent::class,
            'role' => 'assistant',
            'content' => (string) Arr::get($payload, 'answer', config('ai-copilot.fallback.message')),
            'attachments' => '[]',
            'tool_calls' => '[]',
            'tool_results' => '[]',
            'usage' => '[]',
            'meta' => json_encode([
                'payload_source' => $payloadSource,
                'payload_meta' => Arr::get($payload, 'meta'),
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->touchConversation($conversationId);
    }

    protected function touchConversation(string $conversationId): void
    {
        $this->database->table('agent_conversations')
            ->where('id', $conversationId)
            ->update(['updated_at' => now()]);
    }

    protected function makeAgent(User $actor, ?User $subjectUser): BaseCopilotAgent
    {
        return CopilotProviderProfile::forProvider(config('ai-copilot.providers.default', config('ai.default')))->usesStructuredResponses()
            ? new UsersCopilotAgent($actor, $subjectUser)
            : new UsersGeminiCopilotAgent($actor, $subjectUser);
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function resolveBrowserFakeResponse(?int $subjectUserId): ?array
    {
        $payload = $this->browserFakeTransport->dequeue();

        if ($payload === null) {
            return null;
        }

        $normalized = CopilotStructuredOutput::normalize($payload);

        return $normalized ?? CopilotStructuredOutput::fallback(
            diagnostics: ['reason' => 'invalid_browser_fake_transport'],
            subjectUserId: $subjectUserId,
        );
    }
}
