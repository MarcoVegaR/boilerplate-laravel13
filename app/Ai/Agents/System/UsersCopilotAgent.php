<?php

namespace App\Ai\Agents\System;

use App\Ai\Middleware\AttachCopilotContext;
use App\Ai\Middleware\AuthorizeCopilotAccess;
use App\Ai\Middleware\LogCopilotUsage;
use App\Ai\Middleware\SanitizeCopilotPrompt;
use App\Ai\Support\BaseCopilotAgent;
use App\Ai\Support\CopilotProviderProfile;
use App\Ai\Support\CopilotStructuredOutput;
use App\Ai\Support\UsersCopilotPrompt;
use App\Ai\Tools\System\Users\ActivateUserTool;
use App\Ai\Tools\System\Users\CreateUserTool;
use App\Ai\Tools\System\Users\DeactivateUserTool;
use App\Ai\Tools\System\Users\GetUserDetailTool;
use App\Ai\Tools\System\Users\SearchUsersTool;
use App\Ai\Tools\System\Users\SendUserPasswordResetTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasMiddleware;
use Laravel\Ai\Contracts\HasProviderOptions;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Enums\Lab;
use Stringable;

class UsersCopilotAgent extends BaseCopilotAgent implements Agent, Conversational, HasMiddleware, HasProviderOptions, HasStructuredOutput, HasTools
{
    public function instructions(): Stringable|string
    {
        return UsersCopilotPrompt::instructions(
            $this,
            CopilotProviderProfile::forProvider($this->provider()),
        );
    }

    public function middleware(): array
    {
        return [
            new AuthorizeCopilotAccess,
            new SanitizeCopilotPrompt,
            new AttachCopilotContext,
            new LogCopilotUsage,
        ];
    }

    public function schema(JsonSchema $schema): array
    {
        return CopilotStructuredOutput::schema($schema, $this->structuredOutputProfile());
    }

    public function providerOptions(Lab|string $provider): array
    {
        return [];
    }

    public function tools(): iterable
    {
        return [
            new SearchUsersTool($this->actor()),
            new GetUserDetailTool($this->actor()),
            new ActivateUserTool($this->actor()),
            new DeactivateUserTool($this->actor()),
            new SendUserPasswordResetTool($this->actor()),
            new CreateUserTool($this->actor()),
        ];
    }

    protected function structuredOutputProfile(): string
    {
        return CopilotProviderProfile::forProvider($this->provider())->schemaProfile;
    }
}
