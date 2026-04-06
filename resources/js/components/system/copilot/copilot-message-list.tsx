import { Link } from '@inertiajs/react';
import {
    Bot,
    CheckCircle2,
    HelpCircle,
    ListChecks,
    Search,
    TrendingUp,
    UserRoundSearch,
} from 'lucide-react';

import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import type {
    CopilotActionProposal,
    CopilotActionResult,
    CopilotCard,
    CopilotClarificationCard,
    CopilotMetricsCard,
    CopilotNoticeCard,
    CopilotResponse,
    CopilotSearchResultsCard,
} from '@/lib/copilot';

import { CopilotActionCard } from './copilot-action-card';
import { CopilotMarkdown } from './copilot-markdown';
import { CopilotUserContextCard } from './copilot-user-context-card';

type CopilotTimelineItem =
    | {
          id: string;
          role: 'user';
          content: string;
      }
    | {
          id: string;
          role: 'assistant';
          response: CopilotResponse;
      }
    | {
          id: string;
          role: 'action-result';
          result: CopilotActionResult;
      }
    | {
          id: string;
          role: 'error';
          content: string;
      };

type CopilotMessageListProps = {
    items: CopilotTimelineItem[];
    canConfirmActions: boolean;
    onConfirmAction: (action: CopilotActionProposal) => void;
};

function SearchResultsCard({ card }: { card: CopilotSearchResultsCard }) {
    const users = Array.isArray(card.data.users) ? card.data.users : [];
    const matchingCount = card.data.matching_count ?? card.data.count;

    return (
        <Card className="gap-3 py-4">
            <CardContent className="space-y-3 px-4">
                <div className="flex items-center gap-2 text-sm font-medium">
                    <Search className="size-4" />
                    {card.title ?? 'Resultados de usuarios'}
                </div>
                {card.summary && (
                    <p className="text-sm text-muted-foreground">
                        {card.summary}
                    </p>
                )}
                <div className="flex flex-wrap gap-2 text-xs text-muted-foreground">
                    <span>{matchingCount} coincidencias</span>
                    {card.data.truncated && (
                        <span>Mostrando solo una parte</span>
                    )}
                </div>
                <div className="space-y-2">
                    {users.length > 0 ? (
                        users.map((user) => (
                            <div
                                key={user.id}
                                className="rounded-lg border bg-background p-3 text-sm"
                            >
                                <div className="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <p className="font-medium">
                                            {user.name}
                                        </p>
                                        <p className="text-muted-foreground">
                                            {user.email}
                                        </p>
                                    </div>
                                    <div className="flex flex-wrap gap-2">
                                        <Badge
                                            variant={
                                                user.is_active
                                                    ? 'default'
                                                    : 'secondary'
                                            }
                                        >
                                            {user.is_active
                                                ? 'Activo'
                                                : 'Inactivo'}
                                        </Badge>
                                        <Badge variant="outline">
                                            {user.roles_count} roles
                                        </Badge>
                                    </div>
                                </div>

                                {user.show_href && (
                                    <div className="pt-3">
                                        <Link
                                            href={user.show_href}
                                            className="text-xs font-medium text-primary hover:underline"
                                        >
                                            Abrir perfil
                                        </Link>
                                    </div>
                                )}
                            </div>
                        ))
                    ) : (
                        <div className="rounded-lg border border-dashed bg-muted/30 p-3 text-sm text-muted-foreground">
                            No encontre usuarios validos para mostrar en esta
                            respuesta.
                        </div>
                    )}
                </div>
            </CardContent>
        </Card>
    );
}

function MetricsCard({ card }: { card: CopilotMetricsCard }) {
    return (
        <Card className="gap-3 py-4">
            <CardContent className="space-y-4 px-4">
                <div className="flex items-center gap-2 text-sm font-medium">
                    <TrendingUp className="size-4" />
                    {card.title ?? 'Metrica'}
                </div>
                <div className="rounded-xl border bg-muted/30 p-4">
                    <p className="text-xs tracking-wide text-muted-foreground uppercase">
                        {card.data.metric.label ?? 'Valor'}
                    </p>
                    <p className="pt-2 text-3xl font-semibold">
                        {card.data.metric.value ?? '-'}
                    </p>
                </div>

                {card.summary && (
                    <p className="text-sm text-muted-foreground">
                        {card.summary}
                    </p>
                )}

                {card.data.breakdown.length > 0 && (
                    <div className="grid gap-2 sm:grid-cols-2">
                        {card.data.breakdown.map((item) => (
                            <div
                                key={item.key}
                                className="rounded-lg border bg-background p-3"
                            >
                                <p className="text-xs text-muted-foreground">
                                    {item.label}
                                </p>
                                <p className="pt-1 text-lg font-medium">
                                    {item.value}
                                </p>
                            </div>
                        ))}
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

function ClarificationCard({ card }: { card: CopilotClarificationCard }) {
    return (
        <Card className="gap-3 border-amber-500/20 bg-amber-50/60 py-4 dark:bg-amber-500/5">
            <CardContent className="space-y-3 px-4">
                <div className="flex items-center gap-2 text-sm font-medium text-amber-950 dark:text-amber-100">
                    <HelpCircle className="size-4" />
                    {card.title ?? 'Necesito una aclaracion'}
                </div>
                <p className="text-sm text-amber-900/80 dark:text-amber-100/80">
                    {card.data.question}
                </p>
                {card.data.options.length > 0 && (
                    <div className="space-y-2">
                        {card.data.options.map((option) => (
                            <div
                                key={option.value}
                                className="rounded-lg border border-amber-500/20 bg-background px-3 py-2 text-sm"
                            >
                                {option.label}
                            </div>
                        ))}
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

function NoticeCard({ card }: { card: CopilotNoticeCard }) {
    const roles = Array.isArray((card.data as { roles?: unknown[] }).roles)
        ? (
              card.data as {
                  roles: Array<{
                      id: number;
                      name: string;
                      display_name: string | null;
                  }>;
              }
          ).roles
        : [];
    const permissionLabel =
        typeof (card.data as { permission_label?: unknown })
            .permission_label === 'string'
            ? (card.data as { permission_label: string }).permission_label
            : null;
    const allowed =
        typeof (card.data as { allowed?: unknown }).allowed === 'boolean'
            ? (card.data as { allowed: boolean }).allowed
            : null;

    return (
        <Card className="gap-3 py-4">
            <CardContent className="space-y-3 px-4">
                <div className="flex items-center gap-2 text-sm font-medium">
                    <ListChecks className="size-4" />
                    {card.title ?? 'Informacion adicional'}
                </div>
                {card.summary && (
                    <p className="text-sm text-muted-foreground">
                        {card.summary}
                    </p>
                )}

                {roles.length > 0 && (
                    <div className="flex flex-wrap gap-2">
                        {roles.map((role) => (
                            <Badge key={role.id} variant="outline">
                                {role.display_name ?? role.name}
                            </Badge>
                        ))}
                    </div>
                )}

                {permissionLabel && allowed !== null && (
                    <div className="rounded-lg border bg-background p-3 text-sm">
                        <span className="font-medium">{permissionLabel}: </span>
                        <span className="text-muted-foreground">
                            {allowed ? 'Permitido' : 'No permitido'}
                        </span>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

function renderCard(card: CopilotCard) {
    if (card.kind === 'user_context') {
        return (
            <CopilotUserContextCard
                key={`${card.kind}-${card.title}`}
                card={card}
            />
        );
    }

    if (card.kind === 'search_results') {
        return (
            <SearchResultsCard key={`${card.kind}-${card.title}`} card={card} />
        );
    }

    if (card.kind === 'metrics') {
        return <MetricsCard key={`${card.kind}-${card.title}`} card={card} />;
    }

    if (card.kind === 'clarification') {
        return (
            <ClarificationCard key={`${card.kind}-${card.title}`} card={card} />
        );
    }

    if (card.kind === 'notice') {
        return <NoticeCard key={`${card.kind}-${card.title}`} card={card} />;
    }

    return null;
}

export function CopilotMessageList({
    items,
    canConfirmActions,
    onConfirmAction,
}: CopilotMessageListProps) {
    return (
        <div className="space-y-4 p-4">
            {items.map((item) => {
                if (item.role === 'user') {
                    return (
                        <div key={item.id} className="flex justify-end">
                            <div className="max-w-[85%] rounded-2xl bg-primary px-4 py-3 text-sm text-primary-foreground shadow-sm">
                                {item.content}
                            </div>
                        </div>
                    );
                }

                if (item.role === 'error') {
                    return (
                        <div key={item.id} className="flex justify-start">
                            <div className="max-w-[90%] rounded-2xl border border-destructive/20 bg-destructive/5 px-4 py-3 text-sm text-destructive shadow-sm">
                                {item.content}
                            </div>
                        </div>
                    );
                }

                if (item.role === 'action-result') {
                    return (
                        <div key={item.id} className="flex justify-start">
                            <div className="flex w-full max-w-[92%] gap-3">
                                <div className="mt-1 flex size-8 shrink-0 items-center justify-center rounded-full bg-emerald-500/10 text-emerald-600">
                                    <CheckCircle2 className="size-4" />
                                </div>

                                <div className="min-w-0 flex-1 rounded-2xl border border-emerald-500/20 bg-emerald-50 px-4 py-3 text-sm text-emerald-950 shadow-sm">
                                    <p className="font-medium">
                                        {item.result.summary}
                                    </p>
                                    {item.result.target.kind === 'user' && (
                                        <p className="pt-1 text-emerald-800/80">
                                            {item.result.target.name} ·{' '}
                                            {item.result.target.email}
                                        </p>
                                    )}
                                </div>
                            </div>
                        </div>
                    );
                }

                return (
                    <div key={item.id} className="flex justify-start">
                        <div className="flex w-full max-w-[92%] gap-3">
                            <div className="mt-1 flex size-8 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary">
                                {item.response.intent === 'user_context' ? (
                                    <UserRoundSearch className="size-4" />
                                ) : (
                                    <Bot className="size-4" />
                                )}
                            </div>

                            <div className="min-w-0 flex-1 space-y-3">
                                <div className="rounded-2xl border bg-card px-4 py-3 shadow-sm">
                                    <CopilotMarkdown
                                        content={item.response.answer}
                                    />
                                </div>

                                {item.response.cards.map((card) =>
                                    renderCard(card),
                                )}

                                {item.response.actions.map((action) => (
                                    <CopilotActionCard
                                        key={`${item.id}-${action.action_type}-${action.summary}`}
                                        action={action}
                                        canConfirmAction={
                                            canConfirmActions &&
                                            action.can_execute
                                        }
                                        onConfirm={onConfirmAction}
                                    />
                                ))}

                                {item.response.references.length > 0 && (
                                    <div className="flex flex-wrap gap-2">
                                        {item.response.references.map(
                                            (reference) =>
                                                reference.href ? (
                                                    <Link
                                                        key={`${item.id}-${reference.label}`}
                                                        href={reference.href}
                                                        className="text-xs font-medium text-primary hover:underline"
                                                    >
                                                        {reference.label}
                                                    </Link>
                                                ) : (
                                                    <span
                                                        key={`${item.id}-${reference.label}`}
                                                        className="text-xs text-muted-foreground"
                                                    >
                                                        {reference.label}
                                                    </span>
                                                ),
                                        )}
                                    </div>
                                )}
                            </div>
                        </div>
                    </div>
                );
            })}
        </div>
    );
}

export type { CopilotTimelineItem };
