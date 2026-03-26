import { CalendarRange, Filter, Search } from 'lucide-react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { AuditFilterOptions, AuditFilters } from '@/types';

type AuditFiltersProps = {
    filters: AuditFilters;
    filterOptions: AuditFilterOptions;
    onApply: (filters: AuditFilters) => void;
    onClear: () => void;
    draft: AuditFilters;
    onDraftChange: <K extends keyof AuditFilters>(
        key: K,
        value: AuditFilters[K],
    ) => void;
    summary: {
        total: number;
        sourceLabel: string;
        userLabel: string;
        eventLabel: string;
        from: string;
        to: string;
        perPage: number;
        auditableType?: string;
        auditableId?: string;
    };
};

export function AuditFiltersPanel({
    filterOptions,
    onApply,
    onClear,
    draft,
    onDraftChange,
    summary,
}: AuditFiltersProps) {
    const isSecuritySource = draft.source === 'security';

    function handleSubmit(e: React.FormEvent<HTMLFormElement>) {
        e.preventDefault();
        onApply(draft);
    }

    return (
        <form onSubmit={handleSubmit} className="overflow-hidden">
            <div className="border-b bg-muted/10 px-4 py-4 sm:px-5">
                <div className="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                    <div className="space-y-1.5">
                        <div className="flex items-center gap-2 text-xs font-medium tracking-[0.18em] text-muted-foreground uppercase">
                            <Filter className="size-3.5 text-primary" />
                            Vista actual
                        </div>
                        <div className="flex items-end gap-2">
                            <span className="text-3xl font-semibold tracking-tight tabular-nums">
                                {summary.total}
                            </span>
                            <span className="pb-1 text-sm text-muted-foreground">
                                resultado{summary.total === 1 ? '' : 's'}
                            </span>
                        </div>
                        <p className="text-sm text-muted-foreground">
                            {summary.sourceLabel} · {summary.from} →{' '}
                            {summary.to} · Actor: {summary.userLabel} · Evento:{' '}
                            {summary.eventLabel}
                        </p>
                    </div>

                    <div className="flex flex-wrap gap-2 lg:justify-end">
                        <Badge variant="secondary">{summary.sourceLabel}</Badge>
                        <Badge variant="outline">
                            {summary.perPage} por página
                        </Badge>
                        {summary.auditableType && (
                            <Badge variant="outline">
                                Entidad: {summary.auditableType}
                            </Badge>
                        )}
                        {summary.auditableId && (
                            <Badge variant="outline">
                                ID: {summary.auditableId}
                            </Badge>
                        )}
                    </div>
                </div>
            </div>

            <div className="grid gap-3 p-4 sm:grid-cols-2 xl:grid-cols-4">
                <div className="space-y-1.5">
                    <label className="text-xs font-medium text-muted-foreground">
                        Fuente
                    </label>
                    <Select
                        value={draft.source}
                        onValueChange={(v) =>
                            onDraftChange('source', v as AuditFilters['source'])
                        }
                    >
                        <SelectTrigger>
                            <SelectValue placeholder="Fuente" />
                        </SelectTrigger>
                        <SelectContent>
                            {filterOptions.sources.map((s) => (
                                <SelectItem key={s.value} value={s.value}>
                                    {s.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                <div className="space-y-1.5">
                    <label className="text-xs font-medium text-muted-foreground">
                        Actor
                    </label>
                    <Select
                        value={draft.user_id ?? 'all'}
                        onValueChange={(v) =>
                            onDraftChange(
                                'user_id',
                                v === 'all' ? undefined : v,
                            )
                        }
                    >
                        <SelectTrigger>
                            <SelectValue placeholder="Todos" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Todos</SelectItem>
                            {filterOptions.users.map((u) => (
                                <SelectItem key={u.value} value={u.value}>
                                    {u.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                <div className="space-y-1.5">
                    <label className="text-xs font-medium text-muted-foreground">
                        Evento
                    </label>
                    <Select
                        value={draft.event ?? 'all'}
                        onValueChange={(v) =>
                            onDraftChange('event', v === 'all' ? undefined : v)
                        }
                    >
                        <SelectTrigger>
                            <SelectValue placeholder="Todos" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Todos</SelectItem>
                            {filterOptions.events.map((o) => (
                                <SelectItem key={o.value} value={o.value}>
                                    {o.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                <div className="space-y-1.5">
                    <label className="text-xs font-medium text-muted-foreground">
                        Orden
                    </label>
                    <Select
                        value={draft.sort}
                        onValueChange={(v) =>
                            onDraftChange('sort', v as AuditFilters['sort'])
                        }
                    >
                        <SelectTrigger>
                            <SelectValue placeholder="Orden" />
                        </SelectTrigger>
                        <SelectContent>
                            {filterOptions.sortableColumns.map((col) => (
                                <SelectItem key={col} value={col}>
                                    {sortLabels[col]}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                <div className="space-y-1.5">
                    <label className="text-xs font-medium text-muted-foreground">
                        Desde
                    </label>
                    <div className="relative">
                        <CalendarRange className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            type="date"
                            value={draft.from}
                            onChange={(e) =>
                                onDraftChange('from', e.target.value)
                            }
                            className="pl-9"
                        />
                    </div>
                </div>

                <div className="space-y-1.5">
                    <label className="text-xs font-medium text-muted-foreground">
                        Hasta
                    </label>
                    <div className="relative">
                        <CalendarRange className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            type="date"
                            value={draft.to}
                            onChange={(e) =>
                                onDraftChange('to', e.target.value)
                            }
                            className="pl-9"
                        />
                    </div>
                </div>

                <div className="space-y-1.5">
                    <label className="text-xs font-medium text-muted-foreground">
                        Entidad
                    </label>
                    <Select
                        value={draft.auditable_type ?? 'all'}
                        onValueChange={(v) =>
                            onDraftChange(
                                'auditable_type',
                                v === 'all' ? undefined : v,
                            )
                        }
                        disabled={isSecuritySource}
                    >
                        <SelectTrigger>
                            <SelectValue placeholder="Todas" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Todas</SelectItem>
                            {filterOptions.auditableTypes.map((o) => (
                                <SelectItem key={o.value} value={o.value}>
                                    {o.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                <div className="space-y-1.5">
                    <label className="text-xs font-medium text-muted-foreground">
                        ID entidad
                    </label>
                    <div className="relative">
                        <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            type="number"
                            min="1"
                            placeholder="Ej. 42"
                            value={draft.auditable_id ?? ''}
                            onChange={(e) =>
                                onDraftChange(
                                    'auditable_id',
                                    e.target.value || undefined,
                                )
                            }
                            disabled={isSecuritySource}
                            className="pl-9"
                        />
                    </div>
                </div>
            </div>

            <div className="flex flex-wrap items-center justify-between gap-3 border-t px-4 py-3">
                <p className="text-xs text-muted-foreground">
                    Ajusta filtros y aplica para refrescar listado, exportación
                    y detalle.
                </p>
                <div className="flex items-center gap-2">
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={onClear}
                    >
                        Limpiar
                    </Button>
                    <Button type="submit" size="sm">
                        Aplicar filtros
                    </Button>
                </div>
            </div>
        </form>
    );
}

const sortLabels: Record<AuditFilters['sort'], string> = {
    timestamp: 'Fecha',
    actor_name: 'Actor',
    subject_label: 'Entidad',
    ip_address: 'IP',
};
