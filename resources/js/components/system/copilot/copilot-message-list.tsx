import { Link } from '@inertiajs/react';
import { Bot, CheckCircle2, Search, UserRoundSearch } from 'lucide-react';

import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import type {
    CopilotActionProposal,
    CopilotActionResult,
    CopilotCard,
    CopilotResponse,
    CopilotSearchResultsCard,
} from '@/lib/copilot';

import { CopilotActionCard } from './copilot-action-card';
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
                <div className="space-y-2">
                    {card.data.users.map((user) => (
                        <div
                            key={user.id}
                            className="rounded-lg border bg-background p-3 text-sm"
                        >
                            <div className="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <p className="font-medium">{user.name}</p>
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
                                        {user.is_active ? 'Activo' : 'Inactivo'}
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
                    ))}
                </div>
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

    return (
        <Card
            key={`${card.kind}-${card.title}`}
            className="gap-3 border-dashed py-4"
        >
            <CardContent className="px-4 text-sm text-muted-foreground">
                <p className="font-medium text-foreground">
                    {card.title ?? 'Nota'}
                </p>
                {card.summary && <p className="pt-1">{card.summary}</p>}
            </CardContent>
        </Card>
    );
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
                                <div className="rounded-2xl border bg-card px-4 py-3 text-sm shadow-sm">
                                    <p>{item.response.answer}</p>
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
