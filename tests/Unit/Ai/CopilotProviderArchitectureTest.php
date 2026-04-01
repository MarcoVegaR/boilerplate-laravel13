<?php

use App\Ai\Agents\System\UsersGeminiCopilotAgent;
use App\Ai\Support\CopilotProviderProfile;
use App\Models\User;
use Laravel\Ai\Contracts\HasTools;
use Tests\TestCase;

uses(TestCase::class);

it('feature gates gemini to the text json adapter path while preserving provider specific schema metadata', function () {
    $profile = CopilotProviderProfile::forProvider('gemini');

    expect($profile->usesTextJsonResponses())->toBeTrue()
        ->and($profile->usesStructuredResponses())->toBeFalse()
        ->and($profile->supportsStructuredOutput)->toBeFalse()
        ->and($profile->supportsToolsWithStructuredOutput)->toBeFalse()
        ->and($profile->schemaProfile)->toBe('gemini');
});

it('keeps structured output enabled for non gemini providers', function () {
    $profile = CopilotProviderProfile::forProvider('openai');

    expect($profile->usesStructuredResponses())->toBeTrue()
        ->and($profile->usesTextJsonResponses())->toBeFalse()
        ->and($profile->supportsStructuredOutput)->toBeTrue()
        ->and($profile->supportsToolsWithStructuredOutput)->toBeTrue()
        ->and($profile->schemaProfile)->toBe('default');
});

it('keeps the gemini adapter on the text-json path without sdk tool schemas', function () {
    config()->set('ai-copilot.providers.default', 'gemini');

    $agent = new UsersGeminiCopilotAgent(User::factory()->make());

    expect($agent)->not->toBeInstanceOf(HasTools::class)
        ->and((string) $agent->instructions())
        ->toContain('backend ejecuta capacidades locales seguras')
        ->toContain('No menciones tools')
        ->toContain('texto JSON sin tool-calling del SDK');
});
