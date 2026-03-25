import { LayoutDashboard, ShieldCheck, Users, Eye } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';

/**
 * Maps backend icon name strings to Lucide React components.
 * Used by sidebar and header navigation to resolve serialized icon names.
 */
const iconMap: Record<string, LucideIcon> = {
    'layout-dashboard': LayoutDashboard,
    'shield-check': ShieldCheck,
    users: Users,
    eye: Eye,
};

export function resolveIcon(name?: string | null): LucideIcon | undefined {
    if (!name) {
        return undefined;
    }

    return iconMap[name];
}

/**
 * Friendly labels for permission group keys.
 * Centralised so every component (show pages, pickers, etc.) stays consistent.
 */
const groupLabels: Record<string, string> = {
    'system.roles': 'Gestión de Roles',
    'system.users': 'Gestión de Usuarios',
    'system.permissions': 'Gestión de Permisos',
};

export function groupLabel(key: string): string {
    return groupLabels[key] ?? key;
}
