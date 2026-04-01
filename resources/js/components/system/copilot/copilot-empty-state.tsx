import { Bot, Search, ShieldCheck, UserRoundSearch } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { EmptyState } from '@/components/ui/empty-state';

type SubjectUser = {
    id: number;
    name: string;
    email: string;
};

type CopilotEmptyStateProps = {
    canViewUsers: boolean;
    canAssignRoles: boolean;
    canExecuteCopilot?: boolean;
    subjectUser?: SubjectUser | null;
    onSelectPrompt: (prompt: string) => void;
};

export function CopilotEmptyState({
    canViewUsers,
    canAssignRoles,
    canExecuteCopilot = false,
    subjectUser,
    onSelectPrompt,
}: CopilotEmptyStateProps) {
    const prompts = subjectUser
        ? [
              {
                  icon: UserRoundSearch,
                  label: `Resume el estado actual de ${subjectUser.name}`,
                  prompt: `Resume el estado actual de ${subjectUser.name} (${subjectUser.email}).`,
              },
              {
                  icon: ShieldCheck,
                  label: `Explica roles y permisos de ${subjectUser.name}`,
                  prompt: `Explica los roles y permisos efectivos de ${subjectUser.name}.`,
              },
              ...(canExecuteCopilot
                  ? [
                        {
                            icon: Search,
                            label: `Propón desactivar a ${subjectUser.name}`,
                            prompt: `Propón desactivar a ${subjectUser.name} y explica si puedo confirmarlo.`,
                        },
                    ]
                  : []),
          ]
        : [
              ...(canViewUsers
                  ? [
                        {
                            icon: Search,
                            label: 'Buscar usuarios inactivos',
                            prompt: 'Busca usuarios inactivos y resume su estado actual.',
                        },
                    ]
                  : []),
              ...(canAssignRoles
                  ? [
                        {
                            icon: ShieldCheck,
                            label: 'Explicar accesos efectivos',
                            prompt: 'Explica como revisar roles activos y permisos efectivos de un usuario.',
                        },
                    ]
                  : []),
              ...(canExecuteCopilot
                  ? [
                        {
                            icon: UserRoundSearch,
                            label: 'Proponer un restablecimiento',
                            prompt: 'Propón enviar un correo de restablecimiento a un usuario que lo necesite.',
                        },
                    ]
                  : []),
          ];

    return (
        <div className="flex h-full items-center justify-center px-4 py-8">
            <EmptyState
                icon={Bot}
                title="Copiloto de usuarios"
                description="Haz preguntas de lectura sobre cuentas, estados y accesos del modulo de usuarios."
                action={
                    <div className="flex flex-wrap justify-center gap-2">
                        {prompts.map((item) => (
                            <Button
                                key={item.label}
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={() => onSelectPrompt(item.prompt)}
                            >
                                <item.icon className="size-4" />
                                {item.label}
                            </Button>
                        ))}
                    </div>
                }
            />
        </div>
    );
}
