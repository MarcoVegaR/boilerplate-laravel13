<?php

use App\Models\Permission;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

$GLOBALS['generated_scaffold_tokens'] ??= [];
$GLOBALS['generated_scaffold_backups'] ??= [];

afterEach(function () {
    cleanupGeneratedScaffoldArtifacts();
});

it('verifies a writable scaffold after the documented manual integration steps', function () {
    $module = 'verify-'.Str::lower(Str::random(6));
    $model = Str::studly($module).'Module';
    $resource = Str::plural(Str::kebab($model));

    $GLOBALS['generated_scaffold_tokens'][] = $module;

    $this->artisan(sprintf(
        'make:scaffold %s %s --resource=%s --field=name:string:required:list:search:sort --field=notes:text:nullable --field=is_active:boolean:list --nav-label="%s" --nav-icon=boxes',
        $module,
        $model,
        $resource,
        Str::headline($resource),
    ))
        ->expectsOutputToContain('Manual integration still required')
        ->expectsOutputToContain("require __DIR__.'/{$module}.php';")
        ->expectsOutputToContain('$this->call('.Str::studly($module).Str::studly(Str::singular($resource)).'PermissionsSeeder::class);')
        ->assertSuccessful();

    integrateGeneratedModule($module, $resource, $model);
    $this->refreshApplication();

    $this->artisan('migrate:fresh --seed --no-interaction')->assertSuccessful();
    $this->artisan('wayfinder:generate --with-form --no-interaction')->assertSuccessful();

    $controller = file_get_contents(base_path('app/Http/Controllers/'.Str::studly($module).'/'.$model.'Controller.php'));
    $routeFile = file_get_contents(base_path('routes/'.$module.'.php'));
    $typesFile = file_get_contents(base_path('resources/js/types/'.$module.'-'.$resource.'.ts'));
    $indexPage = file_get_contents(base_path('resources/js/pages/'.$module.'/'.$resource.'/index.tsx'));
    $actionFile = base_path('resources/js/actions/App/Http/Controllers/'.Str::studly($module).'/'.$model.'Controller.ts');

    expect($controller)->toContain("Gate::authorize('viewAny', {$model}::class)")
        ->and($controller)->toContain("return Inertia::render('{$module}/{$resource}/index'")
        ->and($routeFile)->toContain('ensure-two-factor')
        ->and($typesFile)->toContain('export type '.$model.'Data')
        ->and($indexPage)->toContain("import { create, destroy, edit, index, show } from '@/actions/App/Http/Controllers/".Str::studly($module)."/{$model}Controller';")
        ->and($indexPage)->toContain('<PageHeader')
        ->and($indexPage)->toContain('handleSort')
        ->and(file_exists($actionFile))->toBeTrue()
        ->and(Route::has($module.'.'.$resource.'.index'))->toBeTrue()
        ->and(Route::has($module.'.'.$resource.'.store'))->toBeTrue()
        ->and(Route::has($module.'.'.$resource.'.destroy'))->toBeTrue();

    expect(Permission::query()->where('name', $module.'.'.$resource.'.view')->exists())->toBeTrue()
        ->and(Permission::query()->where('name', $module.'.'.$resource.'.create')->exists())->toBeTrue()
        ->and(Permission::query()->where('name', $module.'.'.$resource.'.update')->exists())->toBeTrue()
        ->and(Permission::query()->where('name', $module.'.'.$resource.'.delete')->exists())->toBeTrue();
});

it('verifies a read only scaffold after the documented manual integration steps', function () {
    $module = 'readonly-'.Str::lower(Str::random(6));
    $model = Str::studly($module).'Entry';
    $resource = Str::plural(Str::kebab($model));

    $GLOBALS['generated_scaffold_tokens'][] = $module;

    $this->artisan(sprintf(
        'make:scaffold %s %s --resource=%s --field=name:string:required:list:search:sort --field=is_active:boolean:list --read-only',
        $module,
        $model,
        $resource,
    ))->assertSuccessful();

    integrateGeneratedModule($module, $resource, $model);
    $this->refreshApplication();

    $this->artisan('migrate:fresh --seed --no-interaction')->assertSuccessful();
    $this->artisan('wayfinder:generate --with-form --no-interaction')->assertSuccessful();

    $actionFile = base_path('resources/js/actions/App/Http/Controllers/'.Str::studly($module).'/'.$model.'Controller.ts');
    $seederFile = file_get_contents(base_path('database/seeders/'.Str::studly($module).Str::studly(Str::singular($resource)).'PermissionsSeeder.php'));

    expect(file_exists(base_path('app/Http/Requests/'.Str::studly($module).'/'.Str::studly(Str::singular($resource)).'/Store'.$model.'Request.php')))->toBeFalse()
        ->and(file_exists(base_path('resources/js/pages/'.$module.'/'.$resource.'/create.tsx')))->toBeFalse()
        ->and(file_exists($actionFile))->toBeTrue()
        ->and(Route::has($module.'.'.$resource.'.index'))->toBeTrue()
        ->and(Route::has($module.'.'.$resource.'.show'))->toBeTrue()
        ->and(Route::has($module.'.'.$resource.'.store'))->toBeFalse()
        ->and(Route::has($module.'.'.$resource.'.destroy'))->toBeFalse()
        ->and($seederFile)->toContain($module.'.'.$resource.'.view')
        ->and($seederFile)->not->toContain($module.'.'.$resource.'.create')
        ->and($seederFile)->not->toContain($module.'.'.$resource.'.update')
        ->and($seederFile)->not->toContain($module.'.'.$resource.'.delete');
});

function integrateGeneratedModule(string $module, string $resource, string $model): void
{
    $filesystem = app(Filesystem::class);
    $webPath = base_path('routes/web.php');
    $seederPath = base_path('database/seeders/DatabaseSeeder.php');
    $seederClass = Str::studly($module).Str::studly(Str::singular($resource)).'PermissionsSeeder';

    backupFileOnce($webPath);
    backupFileOnce($seederPath);

    $webContents = file_get_contents($webPath) ?: '';
    $requireLine = "require __DIR__.'/{$module}.php';";

    if (! str_contains($webContents, $requireLine)) {
        $webContents = rtrim($webContents).PHP_EOL.$requireLine.PHP_EOL;
        $filesystem->put($webPath, $webContents);
    }

    $seederContents = file_get_contents($seederPath) ?: '';
    $callLine = "        \$this->call({$seederClass}::class);";

    if (! str_contains($seederContents, $callLine)) {
        $seederContents = str_replace(
            '        $this->call(AuditModulePermissionsSeeder::class);',
            '        $this->call(AuditModulePermissionsSeeder::class);'.PHP_EOL.$callLine,
            $seederContents,
        );
        $filesystem->put($seederPath, $seederContents);
    }
}

function backupFileOnce(string $path): void
{
    if (! array_key_exists($path, $GLOBALS['generated_scaffold_backups'])) {
        $GLOBALS['generated_scaffold_backups'][$path] = file_get_contents($path);
    }
}

function cleanupGeneratedScaffoldArtifacts(): void
{
    $filesystem = app(Filesystem::class);

    foreach ($GLOBALS['generated_scaffold_tokens'] as $token) {
        $migrationNeedles = array_unique([
            str_replace('-', '_', $token),
            Str::snake(Str::studly($token)),
            str_replace('-', '', $token),
        ]);

        foreach ([
            base_path('app/Http/Controllers/'.Str::studly($token)),
            base_path('app/Http/Requests/'.Str::studly($token)),
            base_path('resources/js/pages/'.$token),
            base_path('resources/js/actions/App/Http/Controllers/'.Str::studly($token)),
            base_path('tests/Feature/'.Str::studly($token)),
        ] as $directory) {
            if ($filesystem->isDirectory($directory)) {
                $filesystem->deleteDirectory($directory);
            }
        }

        foreach ([
            base_path('routes/'.$token.'.php'),
        ] as $file) {
            if ($filesystem->exists($file)) {
                $filesystem->delete($file);
            }
        }

        foreach (glob(base_path('app/Models/'.Str::studly($token).'*')) ?: [] as $path) {
            $filesystem->delete($path);
        }

        foreach (glob(base_path('app/Policies/'.Str::studly($token).'*')) ?: [] as $path) {
            $filesystem->delete($path);
        }

        foreach (glob(base_path('database/factories/'.Str::studly($token).'*')) ?: [] as $path) {
            $filesystem->delete($path);
        }

        foreach (glob(base_path('database/seeders/'.Str::studly($token).'*')) ?: [] as $path) {
            $filesystem->delete($path);
        }

        foreach (glob(base_path('resources/js/types/'.$token.'-*')) ?: [] as $path) {
            $filesystem->delete($path);
        }

        foreach (glob(base_path('resources/js/routes/'.$token.'*')) ?: [] as $path) {
            $filesystem->delete($path);
        }

        foreach ($migrationNeedles as $needle) {
            foreach (glob(base_path('database/migrations/*'.$needle.'*')) ?: [] as $path) {
                $filesystem->delete($path);
            }
        }
    }

    foreach ($GLOBALS['generated_scaffold_backups'] as $path => $contents) {
        $filesystem->put($path, $contents);
    }

    $GLOBALS['generated_scaffold_tokens'] = [];
    $GLOBALS['generated_scaffold_backups'] = [];
}
