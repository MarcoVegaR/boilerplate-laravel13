<?php

namespace App\Providers;

use App\Models\User;
use App\Policies\UserPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

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
}
