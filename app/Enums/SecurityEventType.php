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
}
