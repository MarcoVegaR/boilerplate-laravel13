import { Head, Link, usePage } from '@inertiajs/react';
import { dashboard, login } from '@/routes';
import type { Auth } from '@/types/auth';

type ErrorPageProps = {
    status: 403 | 404 | 500 | 503;
};

const errorContent: Record<
    ErrorPageProps['status'],
    { title: string; description: string }
> = {
    403: {
        title: 'Acceso denegado',
        description:
            'No tienes permiso para ver esta página. Si crees que esto es un error, contacta al administrador del sistema.',
    },
    404: {
        title: 'Página no encontrada',
        description:
            'La página que buscas no existe o ha sido movida. Verifica la URL e intenta de nuevo.',
    },
    500: {
        title: 'Error del servidor',
        description:
            'Ocurrió un error interno en el servidor. El equipo técnico ya fue notificado. Por favor intenta más tarde.',
    },
    503: {
        title: 'Servicio no disponible',
        description:
            'El servicio se encuentra temporalmente fuera de línea por mantenimiento. Por favor intenta en unos minutos.',
    },
};

function useErrorAction(status: ErrorPageProps['status'], isAuthenticated: boolean) {
    if (status === 503) {
        return { label: 'Reintentar', href: null };
    }

    if (isAuthenticated) {
        return { label: 'Ir al panel', href: dashboard() };
    }

    return { label: 'Volver al inicio', href: login() };
}

export default function ErrorPage({ status }: ErrorPageProps) {
    const { auth } = usePage<{ auth: Auth }>().props;
    const isAuthenticated = !!auth?.user;
    const content = errorContent[status];
    const action = useErrorAction(status, isAuthenticated);

    return (
        <>
            <Head title={`${status} — ${content.title}`} />
            <div className="flex min-h-screen items-center justify-center bg-background p-6">
                <main className="w-full max-w-lg rounded-3xl border border-border bg-card p-8 shadow-sm">
                    <div className="flex flex-col gap-6">
                        <div>
                            <p className="text-sm font-medium text-primary">
                                Caracoders Pro Services
                            </p>
                            <p className="mt-3 text-7xl font-bold tracking-tight text-primary/20 dark:text-primary/10">
                                {status}
                            </p>
                            <h1 className="mt-2 text-2xl font-semibold tracking-tight">
                                {content.title}
                            </h1>
                            <p className="mt-3 text-sm text-muted-foreground">
                                {content.description}
                            </p>
                        </div>

                        <div className="flex items-center gap-3">
                            {action.href ? (
                                <Link
                                    href={action.href}
                                    className="inline-flex items-center justify-center rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground shadow-sm transition hover:opacity-90"
                                >
                                    {action.label}
                                </Link>
                            ) : (
                                <button
                                    type="button"
                                    onClick={() => window.location.reload()}
                                    className="inline-flex items-center justify-center rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground shadow-sm transition hover:opacity-90"
                                >
                                    {action.label}
                                </button>
                            )}
                            <button
                                type="button"
                                onClick={() => window.history.back()}
                                className="inline-flex items-center justify-center rounded-lg border border-border bg-background px-4 py-2 text-sm font-medium text-foreground shadow-sm transition hover:bg-muted"
                            >
                                Regresar
                            </button>
                        </div>

                        <p className="text-xs text-muted-foreground/60">
                            Boilerplate Caracoders &mdash; Base interna segura y
                            lista para crecer
                        </p>
                    </div>
                </main>
            </div>
        </>
    );
}
