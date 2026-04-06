<?php

namespace App\Ai\Support;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;

final class UsersCopilotDomainLexicon
{
    public static function normalize(string $value): string
    {
        $normalized = Str::of(Str::lower(Str::ascii($value)))->squish()->value();

        $replacements = [
            '/\bcom\s+permisos\b/u' => 'con permisos',
            '/\badmins?\b/u' => 'admin',
            '/\badministrad(?:or|ora|ores|oras)\b/u' => 'admin',
            '/\badminsitrad(?:or|ora|ores|oras)\b/u' => 'admin',
            '/\bsuper\s+admin\b/u' => 'super-admin',
            '/\bsuper\s+administrad(?:or|ora|ores|oras)\b/u' => 'super-admin',
            '/\bprivilegios?\b/u' => 'permisos',
            '/\bniveles?\s+de\s+acceso\b/u' => 'acceso efectivo',
            '/\bpermisos?\s+de\s+admin\b/u' => 'acceso administrativo',
            '/\bacceso\s+de\s+admin\b/u' => 'acceso administrativo',
            '/\bacceso\s+admin\b/u' => 'acceso administrativo',
            '/\bacceso\s+administrativ[oa]\b/u' => 'acceso administrativo',
            '/\bpermisos?\s+de\s+super-admin\b/u' => 'rol super-admin',
            '/\bacceso\s+de\s+super-admin\b/u' => 'rol super-admin',
            '/\bno\s+tienen\s+roles\b/u' => 'sin roles',
            '/\bno\s+tiene\s+roles\b/u' => 'sin roles',
        ];

        foreach ($replacements as $pattern => $replacement) {
            $normalized = preg_replace($pattern, $replacement, $normalized) ?? $normalized;
        }

        return Str::of($normalized)->squish()->value();
    }

    public static function canonicalRole(string $value): string
    {
        $normalized = self::normalize($value);

        return match (true) {
            preg_match('/\bsuper-admin\b/u', $normalized) === 1 => 'super-admin',
            preg_match('/\badmin\b/u', $normalized) === 1 => 'admin',
            default => trim($normalized),
        };
    }

    public static function accessProfile(string $value): ?string
    {
        $normalized = self::normalize($value);

        if (preg_match('/\b(rol|perfil)\s+admin\b/u', $normalized) === 1) {
            return null;
        }

        return match (true) {
            preg_match('/\bacceso\s+administrativo|permisos\s+administrativos|usuarios\s+admin\b/u', $normalized) === 1 => 'administrative_access',
            preg_match('/\badministradores\s+del\s+sistema\b/u', $normalized) === 1 => 'administrative_access',
            preg_match('/\badmin\s+del\s+sistema\b/u', $normalized) === 1 => 'administrative_access',
            preg_match('/\busuarios\s+admin\b/u', $normalized) === 1 => 'administrative_access',
            preg_match('/\badmin\b/u', $normalized) === 1 && preg_match('/\b(rol|super-admin)\b/u', $normalized) !== 1 => 'administrative_access',
            preg_match('/\brol\s+super-admin\b/u', $normalized) === 1 => 'super_admin_role',
            default => null,
        };
    }

    /**
     * @return list<string>
     */
    public static function roleSearchTerms(string $value): array
    {
        $canonical = self::canonicalRole($value);

        return match ($canonical) {
            'admin' => ['admin'],
            default => $canonical === '' ? [] : [$canonical],
        };
    }

    public static function permissionNameForIntent(string $value): ?string
    {
        $normalized = self::normalize($value);

        $map = [
            'system.roles.create' => ['crear roles', 'crear rol'],
            'system.roles.update' => ['editar roles', 'actualizar roles', 'modificar roles'],
            'system.users.create' => ['crear usuarios', 'crear usuario'],
            'system.users.view' => ['ver usuarios', 'consultar usuarios'],
            'system.users.update' => ['editar usuarios', 'editar usuario', 'actualizar usuarios', 'modificar usuarios'],
            'system.users.deactivate' => ['desactivar usuarios', 'desactivar usuario', 'inhabilitar usuarios'],
            'system.users.send-reset' => ['enviar restablecimiento', 'enviar reset', 'restablecer contrasena', 'restablecer contrasenas', 'resetear contrasena', 'resetear contrasenas'],
            'system.users.assign-role' => ['asignar roles', 'quitar roles', 'cambiar roles', 'gestionar roles'],
        ];

        foreach ($map as $permission => $phrases) {
            foreach ($phrases as $phrase) {
                if (str_contains($normalized, $phrase)) {
                    return $permission;
                }
            }
        }

        return null;
    }

    public static function permissionLabel(string $permission): string
    {
        return Arr::get([
            'system.roles.create' => 'crear roles',
            'system.roles.update' => 'editar roles',
            'system.users.create' => 'crear usuarios',
            'system.users.view' => 'ver usuarios',
            'system.users.update' => 'editar usuarios',
            'system.users.deactivate' => 'desactivar usuarios',
            'system.users.send-reset' => 'enviar restablecimientos de contraseña',
            'system.users.assign-role' => 'asignar o quitar roles a usuarios',
        ], $permission, $permission);
    }
}
