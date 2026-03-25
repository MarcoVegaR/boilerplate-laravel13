import { usePage } from '@inertiajs/react';
import { Toaster } from 'sonner';
import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import { FlashToaster } from '@/components/flash-toaster';
import type { AppLayoutProps } from '@/types';

export default function AppSidebarLayout({
    children,
    breadcrumbs = [],
}: AppLayoutProps) {
    const { ui } = usePage<{ ui: { branding: { company: string } } }>().props;

    return (
        <AppShell variant="sidebar">
            <AppSidebar />
            <AppContent variant="sidebar" className="overflow-x-hidden">
                <AppSidebarHeader breadcrumbs={breadcrumbs} />
                {children}
                <footer className="mt-auto border-t border-border/40 bg-background/90 px-4 py-3 sm:px-6">
                    <p className="text-center text-xs text-muted-foreground/60">
                        © {new Date().getFullYear()} {ui.branding.company}.
                        Todos los derechos reservados.
                    </p>
                </footer>
                <FlashToaster />
            </AppContent>
            <Toaster theme="system" richColors closeButton />
        </AppShell>
    );
}
