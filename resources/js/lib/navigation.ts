import { usePage } from '@inertiajs/react';

/**
 * Role-specific navigation. `primary` items move, in this order, into a "Your work"
 * group at the top of the sidebar; `hidden` items are left out of the sidebar, the
 * command menu and dashboard shortcuts. This is display only: permissions decide what
 * exists, and direct URLs keep working for anyone allowed to open them.
 */
export const ROLE_NAVIGATION: Record<string, { primary: string[]; hidden: string[] }> = {
    technician: { primary: ['/dispatch', '/work-orders', '/tasks', '/customers', '/ai'], hidden: ['/branches', '/organization'] },
    accountant: { primary: ['/invoices', '/payments', '/customers', '/work-orders', '/', '/ai'], hidden: ['/dispatch', '/requests'] },
};

/** True when the current role's navigation hides the section that `href` belongs to. */
export function useNavHidden(): (href: string) => boolean {
    const { organization } = usePage().props;
    const hidden = organization ? (ROLE_NAVIGATION[organization.role]?.hidden ?? []) : [];

    return (href) => {
        const path = href.split('?')[0] ?? href;

        return hidden.some((section) => path === section || path.startsWith(`${section}/`));
    };
}
