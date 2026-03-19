import type { Auth } from '@/types/auth';
import type { SharedUiProps } from '@/types/ui';

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            sidebarOpen: boolean;
            ui: SharedUiProps;
            [key: string]: unknown;
        };
    }
}
