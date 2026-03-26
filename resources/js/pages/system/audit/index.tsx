import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowDown,
    ArrowUp,
    ArrowUpDown,
    Download,
    ScrollText,
} from 'lucide-react';
import { useMemo, useState } from 'react';

import {
    index,
    show,
} from '@/actions/App/Http/Controllers/System/AuditController';
import AuditExportController from '@/actions/App/Http/Controllers/System/AuditExportController';
import { AuditSourceBadge } from '@/components/system/audit-source-badge';
import { PageHeader } from '@/components/system/page-header';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { EmptyState } from '@/components/ui/empty-state';
import { LaravelPagination } from '@/components/ui/pagination';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useCan } from '@/hooks/use-can';
import AppLayout from '@/layouts/app-layout';
import type {
    AuditFilterOptions,
    AuditFilters,
    AuditIndexEvent,
    BreadcrumbItem,
    PaginatedAuditEvents,
} from '@/types';

import { AuditFiltersPanel } from './components/audit-filters';

type Props = {
    events: PaginatedAuditEvents;
    filters: AuditFilters;
    filterOptions: AuditFilterOptions;
};

function formatRelative(value: string | null): string {
    if (!value) {
        return 'Sin fecha';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    const diffMs = date.getTime() - Date.now();
    const diffMin = Math.round(diffMs / 60_000);
    const formatter = new Intl.RelativeTimeFormat('es', { numeric: 'auto' });

    if (Math.abs(diffMin) < 60) {
        return formatter.format(diffMin, 'minute');
    }

    const diffH = Math.round(diffMin / 60);

    if (Math.abs(diffH) < 24) {
        return formatter.format(diffH, 'hour');
    }

    return formatter.format(Math.round(diffH / 24), 'day');
}

function formatExact(value: string | null): string {
    if (!value) {
        return 'Sin fecha';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return new Intl.DateTimeFormat('es-ES', {
        dateStyle: 'medium',
        timeStyle: 'medium',
    }).format(date);
}

function renderSortIcon(activeSort: string, direction: string, column: string) {
    if (activeSort !== column) {
        return (
            <ArrowUpDown className="ml-1 inline size-3.5 text-muted-foreground/50" />
        );
    }

    return direction === 'asc' ? (
        <ArrowUp className="ml-1 inline size-3.5" />
    ) : (
        <ArrowDown className="ml-1 inline size-3.5" />
    );
}

export default function AuditIndex({ events, filters, filterOptions }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Auditoría', href: index.url() },
    ];

    const canExport = useCan('system.audit.export');
    const [draftFilters, setDraftFilters] = useState(filters);

    const exportHref = useMemo(
        () =>
            AuditExportController.url({
                query: {
                    source: filters.source,
                    from: filters.from,
                    to: filters.to,
                    user_id: filters.user_id,
                    event: filters.event,
                    auditable_type: filters.auditable_type,
                    auditable_id: filters.auditable_id,
                    sort: filters.sort,
                    direction: filters.direction,
                },
            }),
        [filters],
    );

    const activeSourceLabel = useMemo(() => {
        return (
            filterOptions.sources.find(
                (option) => option.value === filters.source,
            )?.label ?? 'Todas'
        );
    }, [filterOptions.sources, filters.source]);

    const activeEventLabel = useMemo(() => {
        if (!filters.event) {
            return 'Todos';
        }

        return (
            filterOptions.events.find(
                (option) => option.value === filters.event,
            )?.label ?? filters.event
        );
    }, [filterOptions.events, filters.event]);

    const activeUserLabel = useMemo(() => {
        if (!filters.user_id) {
            return 'Todos';
        }

        return (
            filterOptions.users.find(
                (option) => option.value === filters.user_id,
            )?.label ?? `ID ${filters.user_id}`
        );
    }, [filterOptions.users, filters.user_id]);

    function applyFilters(nextFilters: AuditFilters) {
        router.get(
            index.url(),
            {
                source: nextFilters.source,
                from: nextFilters.from,
                to: nextFilters.to,
                user_id: nextFilters.user_id || undefined,
                event: nextFilters.event || undefined,
                auditable_type:
                    nextFilters.source === 'security'
                        ? undefined
                        : nextFilters.auditable_type || undefined,
                auditable_id:
                    nextFilters.source === 'security'
                        ? undefined
                        : nextFilters.auditable_id || undefined,
                sort: nextFilters.sort,
                direction: nextFilters.direction,
            },
            { preserveState: true, replace: true },
        );
    }

    function handleDraftChange<K extends keyof AuditFilters>(
        key: K,
        value: AuditFilters[K],
    ) {
        setDraftFilters((current) => {
            const next = { ...current, [key]: value };

            if (key === 'source' && value === 'security') {
                next.auditable_type = undefined;
                next.auditable_id = undefined;
            }

            return next;
        });
    }

    function clearFilters() {
        const nextFilters: AuditFilters = {
            ...filters,
            source: 'all',
            user_id: undefined,
            event: undefined,
            auditable_type: undefined,
            auditable_id: undefined,
            sort: 'timestamp',
            direction: 'desc',
        };

        setDraftFilters(nextFilters);
        applyFilters(nextFilters);
    }

    function handleSort(column: AuditFilters['sort']) {
        const direction =
            filters.sort === column && filters.direction === 'asc'
                ? 'desc'
                : 'asc';

        applyFilters({
            ...filters,
            sort: column,
            direction,
        });
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Auditoría" />

            <div className="space-y-6 px-4 py-6 sm:px-6">
                <PageHeader
                    icon={ScrollText}
                    title="Auditoría"
                    description="Consulta cambios de modelos y eventos de seguridad del sistema."
                    actions={
                        canExport ? (
                            <Button asChild variant="outline" size="sm">
                                <a href={exportHref}>
                                    <Download className="size-4" />
                                    Exportar CSV
                                </a>
                            </Button>
                        ) : undefined
                    }
                />

                <Card className="gap-0 py-0">
                    <AuditFiltersPanel
                        filters={filters}
                        filterOptions={filterOptions}
                        onApply={applyFilters}
                        onClear={clearFilters}
                        draft={draftFilters}
                        onDraftChange={handleDraftChange}
                        summary={{
                            total: events.total,
                            sourceLabel: activeSourceLabel,
                            userLabel: activeUserLabel,
                            eventLabel: activeEventLabel,
                            from: filters.from,
                            to: filters.to,
                            perPage: 20,
                            auditableType: filters.auditable_type,
                            auditableId: filters.auditable_id,
                        }}
                    />
                </Card>

                {events.data.length === 0 ? (
                    <Card className="py-0">
                        <EmptyState
                            icon={ScrollText}
                            title="Sin eventos para mostrar"
                            description="Ajusta la ventana o los filtros activos para encontrar registros de auditoría."
                        />
                    </Card>
                ) : (
                    <>
                        <Card className="gap-0 overflow-hidden py-0">
                            <TooltipProvider>
                                <Table>
                                    <TableHeader>
                                        <TableRow className="bg-muted/40 hover:bg-muted/40">
                                            <TableHead>
                                                <button
                                                    type="button"
                                                    className="inline-flex items-center hover:text-foreground"
                                                    onClick={() =>
                                                        handleSort('timestamp')
                                                    }
                                                >
                                                    Fecha
                                                    {renderSortIcon(
                                                        filters.sort,
                                                        filters.direction,
                                                        'timestamp',
                                                    )}
                                                </button>
                                            </TableHead>
                                            <TableHead>Fuente</TableHead>
                                            <TableHead>
                                                <button
                                                    type="button"
                                                    className="inline-flex items-center hover:text-foreground"
                                                    onClick={() =>
                                                        handleSort('actor_name')
                                                    }
                                                >
                                                    Actor
                                                    {renderSortIcon(
                                                        filters.sort,
                                                        filters.direction,
                                                        'actor_name',
                                                    )}
                                                </button>
                                            </TableHead>
                                            <TableHead>Evento</TableHead>
                                            <TableHead>
                                                <button
                                                    type="button"
                                                    className="inline-flex items-center hover:text-foreground"
                                                    onClick={() =>
                                                        handleSort(
                                                            'subject_label',
                                                        )
                                                    }
                                                >
                                                    Entidad
                                                    {renderSortIcon(
                                                        filters.sort,
                                                        filters.direction,
                                                        'subject_label',
                                                    )}
                                                </button>
                                            </TableHead>
                                            <TableHead>
                                                <button
                                                    type="button"
                                                    className="inline-flex items-center hover:text-foreground"
                                                    onClick={() =>
                                                        handleSort('ip_address')
                                                    }
                                                >
                                                    IP
                                                    {renderSortIcon(
                                                        filters.sort,
                                                        filters.direction,
                                                        'ip_address',
                                                    )}
                                                </button>
                                            </TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {events.data.map((event) => (
                                            <EventRow
                                                key={event.id}
                                                event={event}
                                            />
                                        ))}
                                    </TableBody>
                                </Table>
                            </TooltipProvider>
                        </Card>

                        <LaravelPagination
                            links={events.links}
                            from={events.from}
                            to={events.to}
                            total={events.total}
                        />
                    </>
                )}
            </div>
        </AppLayout>
    );
}

function EventRow({ event }: { event: AuditIndexEvent }) {
    return (
        <TableRow
            className="group cursor-pointer transition-colors hover:bg-muted/40"
            onClick={() =>
                router.get(
                    show.url({
                        source: event.source,
                        id: event.source_record_id,
                    }),
                )
            }
        >
            <TableCell>
                <Tooltip>
                    <TooltipTrigger asChild>
                        <span className="text-sm font-medium">
                            {formatRelative(event.timestamp)}
                        </span>
                    </TooltipTrigger>
                    <TooltipContent side="top">
                        {formatExact(event.timestamp)}
                    </TooltipContent>
                </Tooltip>
            </TableCell>
            <TableCell>
                <AuditSourceBadge
                    source={event.source}
                    label={event.source_label}
                />
            </TableCell>
            <TableCell>
                <div className="space-y-0.5">
                    {event.actor_href ? (
                        <Link
                            href={event.actor_href}
                            className="text-sm font-medium hover:underline"
                            onClick={(e) => e.stopPropagation()}
                        >
                            {event.actor_name ?? 'Sistema'}
                        </Link>
                    ) : (
                        <span className="text-sm font-medium">
                            {event.actor_name ?? 'Sistema'}
                        </span>
                    )}
                </div>
            </TableCell>
            <TableCell>
                <span className="text-sm">{event.event_label}</span>
            </TableCell>
            <TableCell>
                <div className="space-y-0.5">
                    {event.subject_href ? (
                        <Link
                            href={event.subject_href}
                            className="text-sm font-medium hover:underline"
                            onClick={(e) => e.stopPropagation()}
                        >
                            {event.subject_label ?? 'Sin entidad'}
                        </Link>
                    ) : (
                        <Link
                            href={show.url({
                                source: event.source,
                                id: event.source_record_id,
                            })}
                            className="text-sm font-medium transition-colors hover:text-foreground hover:underline"
                            onClick={(e) => e.stopPropagation()}
                        >
                            {event.subject_label ?? 'Sin entidad'}
                        </Link>
                    )}
                    {event.subject_type && (
                        <p className="text-xs text-muted-foreground">
                            {event.subject_type}
                        </p>
                    )}
                </div>
            </TableCell>
            <TableCell>
                <code className="rounded bg-muted px-2 py-1 text-xs">
                    {event.ip_address ?? '—'}
                </code>
            </TableCell>
        </TableRow>
    );
}
