import '@inertiajs/core';

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            locale: string;
            dir: 'ltr' | 'rtl';
            auth: {
                user: {
                    id: string;
                    name: string;
                    email: string;
                } | null;
            };
            organization: {
                id: string;
                name: string;
                slug: string;
                locale: string;
                currency: string;
                country: string;
                timezone: string;
                status: string;
                role: string;
            } | null;
            permissions: string[];
            flash: {
                success: string | null;
                status: string | null;
            };
        };
    }
}
