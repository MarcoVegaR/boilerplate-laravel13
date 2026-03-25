/**
 * System module TypeScript types for PRD-05 Access Administration.
 * Covers roles, permissions, and user-with-roles shapes.
 */

import type { PaginatedData } from './ui';

export type RoleData = {
    id: number;
    name: string;
    display_name: string | null;
    description: string | null;
    guard_name: string;
    is_active: boolean;
    permissions_count?: number;
    users_count?: number;
    permissions?: PermissionData[];
    created_at: string;
    updated_at: string;
};

export type PermissionData = {
    id: number;
    name: string;
    display_name: string | null;
    description: string | null;
    guard_name: string;
    created_at: string;
    updated_at: string;
};

/** Permissions grouped by context key (first two dot-segments of `name`). */
export type GroupedPermissions = Record<string, PermissionData[]>;

export type EffectivePermission = {
    id: number;
    name: string;
    display_name: string | null;
    /** Map of role_name → role_display_name that grants this permission. */
    roles: Record<string, string>;
};

export type GroupedEffectivePermissions = Record<string, EffectivePermission[]>;

export type UserWithRoles = {
    id: number;
    name: string;
    email: string;
    is_active: boolean;
    email_verified_at: string | null;
    two_factor_confirmed_at: string | null;
    roles: RoleData[];
    last_login_at?: string | null;
    created_at: string;
    updated_at: string;
};

export type BulkActionPayload = {
    action: 'deactivate' | 'activate' | 'delete';
    ids: number[];
};

export type PaginatedRoles = PaginatedData<RoleData>;
export type PaginatedUsers = PaginatedData<UserWithRoles>;
export type PaginatedPermissions = PaginatedData<PermissionData>;
