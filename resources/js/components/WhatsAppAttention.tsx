import { Link } from '@inertiajs/react';
import { TriangleAlert } from 'lucide-react';
import { useI18n } from '@/i18n';
import { useFormat } from '@/lib/format';

export type WhatsAppHealth = { needs_attention: boolean; reason: string | null; since: string | null } | null;

/**
 * Shown on the WhatsApp pages while Meta rejects the organization's access token. The
 * text comes from the reason code only; Meta's own error text is never stored.
 */
export function WhatsAppAttention({ health, canManageSettings, showSettingsLink = true }: { health: WhatsAppHealth; canManageSettings: boolean; showSettingsLink?: boolean }) {
    const { t } = useI18n();
    const format = useFormat();

    if (!health?.needs_attention) {
        return null;
    }

    const copy = t.whatsapp.attention;

    return (
        <div data-testid="whatsapp-attention" role="alert" className="mb-4 flex gap-3 rounded-card border border-danger-border bg-danger-soft px-4 py-3 text-body text-danger">
            <TriangleAlert className="mt-0.5 size-5 shrink-0" strokeWidth={1.75} aria-hidden="true" />
            <div className="min-w-0 space-y-1">
                <p className="font-semibold">{copy.title}</p>
                <p>{(health.reason && copy.reasons[health.reason]) || copy.other}</p>
                <p>{canManageSettings ? copy.fix : copy.askAdmin}</p>
                <p className="flex flex-wrap gap-x-4 gap-y-1">
                    {health.since ? (
                        <span className="text-caption tabular-nums">
                            {copy.since}: {format.dateTime(health.since)}
                        </span>
                    ) : null}
                    <Link href="/whatsapp/setup-guide" className="font-medium underline underline-offset-2">
                        {copy.guide}
                    </Link>
                    {canManageSettings && showSettingsLink ? (
                        <Link href="/settings/integrations/whatsapp" className="font-medium underline underline-offset-2">
                            {copy.settings}
                        </Link>
                    ) : null}
                </p>
            </div>
        </div>
    );
}
