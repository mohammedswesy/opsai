import { usePage } from '@inertiajs/react';

export function can(permissions: string[], permission: string): boolean {
    return permissions.includes('*') || permissions.includes(permission);
}

/** Returns a checker bound to the current user's organization permissions. */
export function useCan(): (permission: string) => boolean {
    const { permissions } = usePage().props;

    return (permission) => can(permissions, permission);
}
