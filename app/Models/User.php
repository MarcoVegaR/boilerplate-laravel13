<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\TwoFactorAuthenticatable;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements Auditable, MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable, \OwenIt\Auditing\Auditable, TwoFactorAuthenticatable;

    /**
     * Defense-in-depth: also excluded globally in config/audit.php.
     *
     * @var list<string>
     */
    protected $auditExclude = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /**
     * Boot the User model.
     * Register the last-super-admin deletion guard BEFORE trait boot methods
     * so our check fires before Spatie's `bootHasRoles` detaches roles from `model_has_roles`.
     *
     * We override boot() (rather than a trait boot method) to guarantee priority.
     * The check queries the pivot table directly, so it's reliable even if Spatie
     * has already mutated the relationship state.
     */
    protected static function boot(): void
    {
        static::deleting(function (self $user): void {
            if (static::isLastSuperAdmin($user)) {
                throw ValidationException::withMessages([
                    'role' => __('No es posible eliminar al último usuario con el rol super-admin.'),
                ]);
            }
        });

        parent::boot();
    }

    /**
     * Determine whether the given user is the last user holding the super-admin role.
     * Queries the `model_has_roles` pivot directly so this remains reliable even when
     * called from a `deleting` observer (where Spatie may have already detached roles).
     */
    public static function isLastSuperAdmin(self $user): bool
    {
        $superAdminRoleId = DB::table('roles')
            ->where('name', 'super-admin')
            ->where('guard_name', 'web')
            ->value('id');

        if ($superAdminRoleId === null) {
            return false;
        }

        $userHasSuperAdmin = DB::table('model_has_roles')
            ->where('role_id', $superAdminRoleId)
            ->where('model_type', (new static)->getMorphClass())
            ->where('model_id', $user->id)
            ->exists();

        if (! $userHasSuperAdmin) {
            return false;
        }

        $totalSuperAdminCount = DB::table('model_has_roles')
            ->where('role_id', $superAdminRoleId)
            ->where('model_type', (new static)->getMorphClass())
            ->count();

        return $totalSuperAdminCount <= 1;
    }

    /**
     * Remove the super-admin role from this user, with a last-admin safeguard.
     * Use this method instead of removeRole('super-admin') directly.
     *
     * @throws ValidationException when attempting to remove the last super-admin
     */
    public function removeSuperAdminRole(): static
    {
        if (static::isLastSuperAdmin($this)) {
            throw ValidationException::withMessages([
                'role' => __('No es posible quitar el rol super-admin al último administrador del sistema.'),
            ]);
        }

        return $this->removeRole('super-admin');
    }
}
