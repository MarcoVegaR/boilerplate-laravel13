<?php

namespace App\Ai\Support;

use App\Models\User;

class CopilotActionProposal
{
    /**
     * @param  array<string, mixed>  $target
     * @param  array<string, mixed>  $payload
     * @param  list<string>  $requiredPermissions
     * @return array<string, mixed>
     */
    public static function make(
        CopilotActionType $actionType,
        array $target,
        string $summary,
        array $payload,
        bool $canExecute,
        ?string $denyReason,
        array $requiredPermissions,
    ): array {
        return [
            'kind' => 'action_proposal',
            'action_type' => $actionType->value,
            'target' => $target,
            'summary' => $summary,
            'payload' => $payload,
            'can_execute' => $canExecute,
            'deny_reason' => $denyReason,
            'required_permissions' => $requiredPermissions,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function userTarget(User $user): array
    {
        return [
            'kind' => 'user',
            'user_id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'is_active' => $user->is_active,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function newUserTarget(?string $name, ?string $email): array
    {
        return [
            'kind' => 'new_user',
            'name' => $name,
            'email' => $email,
        ];
    }
}
