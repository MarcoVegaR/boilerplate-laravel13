import type { InertiaLinkProps } from '@inertiajs/react';
import type { ReactNode } from 'react';
import type { BreadcrumbItem } from '@/types/navigation';

export type AppLayoutProps = {
    children: ReactNode;
    breadcrumbs?: BreadcrumbItem[];
};

export type AppVariant = 'header' | 'sidebar';

export type AuthLayoutProps = {
    children?: ReactNode;
    name?: string;
    title?: string;
    description?: string;
};

export type SharedUiLink = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
};

export type SharedUiProps = {
    locale: string;
    branding: {
        application: string;
        company: string;
        footerSubtitle: string;
        shellTagline: string;
        mobileBlurb: string;
    };
    navigation: {
        label: string;
        items: SharedUiLink[];
        starterPromoLinksRemoved: boolean;
    };
    settingsSection: {
        title: string;
        description: string;
        ariaLabel: string;
    };
    settingsNavigation: SharedUiLink[];
    appearance: {
        palette: string;
        defaultMode: 'light' | 'dark' | 'system';
        supportedModes: Array<'light' | 'dark' | 'system'>;
        labels: Record<'light' | 'dark' | 'system', string>;
    };
};
