<?php

namespace App\Enums;

enum SecurityEventType: string
{
    case LoginSuccess = 'login_success';
    case LoginFailed = 'login_failed';
    case Logout = 'logout';
    case TwoFactorEnabled = '2fa_enabled';
    case TwoFactorDisabled = '2fa_disabled';
    case RoleAssigned = 'role_assigned';
    case RoleRevoked = 'role_revoked';

    // User lifecycle events (PRD-05)
    case UserCreated = 'user_created';
    case UserUpdated = 'user_updated';
    case UserDeactivated = 'user_deactivated';
    case UserActivated = 'user_activated';
    case UserDeleted = 'user_deleted';
    case PasswordResetSent = 'password_reset_sent';

    // Role lifecycle events (PRD-05)
    case RoleCreated = 'role_created';
    case RoleUpdated = 'role_updated';
    case RoleDeactivated = 'role_deactivated';
    case RoleActivated = 'role_activated';
    case RoleDeleted = 'role_deleted';
    case PermissionsSynced = 'permissions_synced';
}
