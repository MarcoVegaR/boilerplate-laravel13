<?php

namespace App\Providers;

use App\Listeners\RecordFailedLogin;
use App\Listeners\RecordLogout;
use App\Listeners\RecordRoleAssigned;
use App\Listeners\RecordRoleRevoked;
use App\Listeners\RecordSuccessfulLogin;
use App\Models\User;
use App\Observers\TwoFactorAuditObserver;
use App\Policies\UserPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Events\RoleAttachedEvent;
use Spatie\Permission\Events\RoleDetachedEvent;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configurePolicies();
        $this->configureAuditing();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    /**
     * Register model policies.
     *
     * Permission-based authorization (e.g. $user->can('system.roles.view')) is resolved
     * automatically by Spatie Permission via its register_permission_check_method gate.
     * Manual Gate::define() calls are NOT needed — Spatie handles every permission
     * that exists in the `permissions` table, making permissions fully dynamic/UI-manageable.
     */
    protected function configurePolicies(): void
    {
        Gate::policy(User::class, UserPolicy::class);
    }

    /**
     * Register security event listeners and model observers.
     */
    private function configureAuditing(): void
    {
        Event::listen(Login::class, RecordSuccessfulLogin::class);
        Event::listen(Failed::class, RecordFailedLogin::class);
        Event::listen(Logout::class, RecordLogout::class);
        Event::listen(RoleAttachedEvent::class, RecordRoleAssigned::class);
        Event::listen(RoleDetachedEvent::class, RecordRoleRevoked::class);

        User::observe(TwoFactorAuditObserver::class);
    }
}
