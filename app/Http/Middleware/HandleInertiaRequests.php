<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'ui' => [
                'locale' => app()->getLocale(),
                'branding' => [
                    'application' => 'Boilerplate Caracoders',
                    'company' => 'Caracoders Pro Services',
                    'footerSubtitle' => 'Base interna segura y lista para crecer',
                    'shellTagline' => 'Boilerplate corporativo en español',
                    'mobileBlurb' => 'Baseline corporativo para sistemas internos.',
                ],
                'navigation' => [
                    'label' => 'Navegación',
                    'items' => [
                        [
                            'title' => 'Panel',
                            'href' => route('dashboard', absolute: false),
                        ],
                    ],
                    'starterPromoLinksRemoved' => true,
                ],
                'settingsSection' => [
                    'title' => 'Configuración',
                    'description' => 'Administra tu perfil, seguridad y preferencias visuales',
                    'ariaLabel' => 'Configuración',
                ],
                'settingsNavigation' => [
                    [
                        'title' => 'Perfil',
                        'href' => route('profile.edit', absolute: false),
                    ],
                    [
                        'title' => 'Seguridad',
                        'href' => route('security.edit', absolute: false),
                    ],
                    [
                        'title' => 'Apariencia',
                        'href' => route('appearance.edit', absolute: false),
                    ],
                ],
                'appearance' => [
                    'palette' => 'violet',
                    'defaultMode' => 'light',
                    'supportedModes' => ['light', 'dark', 'system'],
                    'labels' => [
                        'light' => 'Claro',
                        'dark' => 'Oscuro',
                        'system' => 'Sistema',
                    ],
                ],
            ],
        ];
    }
}
