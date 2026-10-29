import type { ReactNode } from 'react';
import { PageHeader } from '@/components/PageHeader';
import { Section } from '@/components/Section';
import { useI18n } from '@/i18n';
import AppLayout from '@/layouts/AppLayout';

/*
 * docs/whatsapp-live-setup.md rendered on the server (raw HTML in the file is escaped).
 * The guide is written in English, so the article keeps a left-to-right direction.
 */
const prose = [
    '[&_h1]:hidden',
    '[&_h2]:mt-6 [&_h2]:mb-2 [&_h2]:text-heading [&_h2]:font-semibold [&_h2]:text-ink',
    '[&_h3]:mt-4 [&_h3]:mb-1 [&_h3]:font-semibold [&_h3]:text-ink',
    '[&_p]:my-2 [&_ul]:my-2 [&_ul]:list-disc [&_ul]:ps-6 [&_ol]:my-2 [&_ol]:list-decimal [&_ol]:ps-6 [&_li]:my-1',
    '[&_a]:text-brand [&_a]:underline',
    '[&_code]:rounded-sm [&_code]:bg-surface-muted [&_code]:px-1 [&_code]:font-mono [&_code]:text-caption',
    '[&_pre]:my-2 [&_pre]:overflow-x-auto [&_pre]:rounded-control [&_pre]:bg-surface-muted [&_pre]:p-3',
    '[&_table]:my-3 [&_table]:block [&_table]:overflow-x-auto [&_td]:border [&_td]:border-line [&_td]:px-2 [&_td]:py-1 [&_th]:border [&_th]:border-line [&_th]:px-2 [&_th]:py-1 [&_th]:text-start',
].join(' ');

export default function Guide({ html }: { html: string }) {
    const { t } = useI18n();

    return (
        <>
            <PageHeader parent={{ label: t.nav.whatsapp, href: '/whatsapp' }} title={t.whatsapp.guide} />
            <Section>
                <article dir="ltr" lang="en" className={`max-w-none text-body text-ink-body ${prose}`} dangerouslySetInnerHTML={{ __html: html }} />
            </Section>
        </>
    );
}

Guide.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
