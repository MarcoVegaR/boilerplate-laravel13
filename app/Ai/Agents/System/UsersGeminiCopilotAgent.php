<?php

namespace App\Ai\Agents\System;

use App\Ai\Middleware\AttachCopilotContext;
use App\Ai\Middleware\AuthorizeCopilotAccess;
use App\Ai\Middleware\LogCopilotUsage;
use App\Ai\Middleware\SanitizeCopilotPrompt;
use App\Ai\Support\BaseCopilotAgent;
use App\Ai\Support\CopilotProviderProfile;
use App\Ai\Support\UsersCopilotPrompt;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasMiddleware;
use Laravel\Ai\Contracts\HasProviderOptions;
use Laravel\Ai\Enums\Lab;
use Stringable;

class UsersGeminiCopilotAgent extends BaseCopilotAgent implements Agent, Conversational, HasMiddleware, HasProviderOptions
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

    public function providerOptions(Lab|string $provider): array
    {
        return [];
    }
}
