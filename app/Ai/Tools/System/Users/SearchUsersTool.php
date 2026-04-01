<?php

namespace App\Ai\Tools\System\Users;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class SearchUsersTool implements Tool
{
    public function __construct(protected User $actor) {}

    public function description(): Stringable|string
    {
        return 'Busca usuarios del sistema con filtros acotados y devuelve un resumen normalizado.';
    }

    public function handle(Request $request): Stringable|string
    {
        if (! $this->actor->can('viewAny', User::class)) {
            throw new AuthorizationException(__('No tienes permiso para consultar usuarios.'));
        }

        $query = User::query()
            ->with('roles')
            ->withCount('roles')
            ->when($request->string('query')->trim()->value(), function (Builder $builder, string $search): void {
                $term = '%'.mb_strtolower($search).'%';
                $useUnaccent = DB::connection()->getDriverName() === 'pgsql';
                $wrap = fn (string $column): string => $useUnaccent
                    ? "unaccent(LOWER({$column})) LIKE unaccent(?)"
                    : "LOWER({$column}) LIKE ?";

                $builder->where(fn (Builder $nested) => $nested
                    ->whereRaw($wrap('name'), [$term])
                    ->orWhereRaw($wrap('email'), [$term])
                );
            })
            ->when($request->string('status')->value(), fn (Builder $builder, string $status) => match ($status) {
                'active' => $builder->where('is_active', true),
                'inactive' => $builder->where('is_active', false),
                default => $builder,
            })
            ->when($request->has('email_verified'), fn (Builder $builder) => $request->boolean('email_verified')
                ? $builder->whereNotNull('email_verified_at')
                : $builder->whereNull('email_verified_at'))
            ->when($request->has('has_roles'), fn (Builder $builder) => $request->boolean('has_roles')
                ? $builder->whereHas('roles')
                : $builder->whereDoesntHave('roles'))
            ->orderByDesc('created_at')
            ->limit(8)
            ->get();

        return json_encode([
            'count' => $query->count(),
            'users' => $query->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_active' => $user->is_active,
                'email_verified' => $user->email_verified_at !== null,
                'two_factor_enabled' => $user->two_factor_confirmed_at !== null,
                'roles_count' => $user->roles_count,
                'roles' => $user->roles
                    ->map(fn ($role): array => [
                        'id' => $role->id,
                        'name' => $role->name,
                        'display_name' => $role->display_name,
                        'is_active' => $role->is_active,
                    ])
                    ->values()
                    ->all(),
                'created_at' => optional($user->created_at)?->toIso8601String(),
                'show_href' => route('system.users.show', ['user' => $user->id], absolute: false),
            ])->values()->all(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->nullable(),
            'status' => $schema->string()->enum(['active', 'inactive', 'all'])->default('all'),
            'email_verified' => $schema->boolean()->nullable(),
            'has_roles' => $schema->boolean()->nullable(),
        ];
    }
}
