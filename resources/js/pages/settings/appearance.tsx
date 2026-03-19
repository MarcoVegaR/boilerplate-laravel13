import { Head, usePage } from '@inertiajs/react';
import AppearanceTabs from '@/components/appearance-tabs';
import Heading from '@/components/heading';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { edit as editAppearance } from '@/routes/appearance';
import type { BreadcrumbItem, SharedUiProps } from '@/types';

const appearanceContent = {
    title: 'Apariencia',
    description:
        'Elige cómo deseas ver la interfaz corporativa con la paleta violeta predeterminada.',
} as const;

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Apariencia',
        href: editAppearance(),
    },
];

export default function Appearance() {
    const { ui } = usePage().props as { ui: SharedUiProps };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Apariencia" />

            <h1 className="sr-only">Configuración de apariencia</h1>

            <SettingsLayout>
                <div className="space-y-6">
                    <Heading
                        variant="small"
                        title={appearanceContent.title}
                        description={appearanceContent.description}
                    />
                    <AppearanceTabs
                        supportedModes={ui.appearance.supportedModes}
                        labels={ui.appearance.labels}
                    />
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
