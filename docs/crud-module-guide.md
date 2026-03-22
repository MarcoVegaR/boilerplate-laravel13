# CRUD Module Guide

This guide defines the conventions for building new administrative CRUD modules in this boilerplate. Follow it to ensure your module is consistent with existing code, fully authorized, and visually correct across all themes and screen sizes.

For the architectural decisions behind these conventions, see [ADR-009](./adr/ADR-009-crud-module-standard.md).

---

## Table of Contents

1. [Module File Checklist](#1-module-file-checklist)
2. [Routing Setup](#2-routing-setup)
3. [Controller Convention](#3-controller-convention)
4. [FormRequest Convention](#4-formrequest-convention)
5. [Policy Convention](#5-policy-convention)
6. [Frontend Page Convention](#6-frontend-page-convention)
7. [Wayfinder Convention](#7-wayfinder-convention)
8. [Breadcrumb Flow](#8-breadcrumb-flow)
9. [Lifecycle Operations](#9-lifecycle-operations)
10. [Five-Layer Security Model](#10-five-layer-security-model)
11. [Checklist A — Module Consistency](#checklist-a--module-consistency)
12. [Checklist B — Lifecycle Operations](#checklist-b--lifecycle-operations)
13. [Checklist C — Soft Delete](#checklist-c--soft-delete)
14. [Checklist D — Visual Verification](#checklist-d--visual-verification)

---

## 1. Module File Checklist

A complete CRUD module requires the following files. Replace `{Module}` with the module namespace (e.g., `System`, `Billing`) and `{Model}` with the model name (e.g., `User`, `Invoice`):

### Backend Files

| File                                                       | Purpose                                           |
| ---------------------------------------------------------- | ------------------------------------------------- |
| `routes/{module}.php`                                      | Route group with resource and lifecycle routes    |
| `app/Http/Controllers/{Module}/{Model}Controller.php`      | Index, create, store, show, edit, update, destroy |
| `app/Http/Requests/{Module}/Store{Model}Request.php`       | Authorize + validate store                        |
| `app/Http/Requests/{Module}/Update{Model}Request.php`      | Authorize + validate update                       |
| `app/Policies/{Model}Policy.php`                           | Policy for all CRUD abilities                     |
| `app/Models/{Model}.php`                                   | Eloquent model (if new)                           |
| `database/factories/{Model}Factory.php`                    | Factory for tests (required)                      |
| `database/migrations/YYYY_MM_DD_create_{model}s_table.php` | Migration (if new table)                          |
| `database/seeders/{Module}PermissionsSeeder.php`           | Permissions for this module                       |

### Frontend Files

| File                                                      | Purpose                                               |
| --------------------------------------------------------- | ----------------------------------------------------- |
| `resources/js/pages/{module}/index.tsx`                   | List view with Toolbar, Table, Pagination, EmptyState |
| `resources/js/pages/{module}/create.tsx`                  | Create form page                                      |
| `resources/js/pages/{module}/edit.tsx`                    | Edit form page                                        |
| `resources/js/pages/{module}/show.tsx`                    | Detail/read view (optional)                           |
| `resources/js/pages/{module}/components/{model}-form.tsx` | Shared form fields component (used by create/edit)    |

### Test Files

| File                                                  | Purpose                         |
| ----------------------------------------------------- | ------------------------------- |
| `tests/Feature/{Module}/{Model}IndexTest.php`         | Index, search, authorization    |
| `tests/Feature/{Module}/{Model}CreateTest.php`        | Create form, store, validation  |
| `tests/Feature/{Module}/{Model}UpdateTest.php`        | Edit form, update, validation   |
| `tests/Feature/{Module}/{Model}DeleteTest.php`        | Destroy, soft delete, lifecycle |
| `tests/Feature/{Module}/{Model}AuthorizationTest.php` | All 5 layers for every action   |

---

## 2. Routing Setup

Each module has its own route file loaded from `routes/web.php`. This keeps the main file clean and makes module routes independently reviewable.

### Module Route File (`routes/{module}.php`)

```php
<?php

use App\Http\Controllers\{Module}\{Model}Controller;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'ensure-two-factor'])
    ->prefix('{module}')
    ->name('{module}.')
    ->group(function () {
        Route::resource('{resource}', {Model}Controller::class)
            ->only(['index', 'create', 'store', 'show', 'edit', 'update', 'destroy']);

        // Lifecycle operations (conditional per module — include only if needed):
        // Route::patch('{resource}/{model}/restore', [Restore{Model}Controller::class, '__invoke'])
        //     ->withTrashed()
        //     ->name('{resource}.restore');
        // Route::delete('{resource}/{model}/force', [ForceDelete{Model}Controller::class, '__invoke'])
        //     ->withTrashed()
        //     ->name('{resource}.force-delete');
    });
```

### Register in `routes/web.php`

```php
// At the end of routes/web.php, after the existing require calls:
require __DIR__.'/{module}.php';
```

### Route Naming Convention

- Index: `{module}.{resource}.index`
- Create: `{module}.{resource}.create`
- Store: `{module}.{resource}.store`
- Show: `{module}.{resource}.show`
- Edit: `{module}.{resource}.edit`
- Update: `{module}.{resource}.update`
- Destroy: `{module}.{resource}.destroy`
- Restore: `{module}.{resource}.restore` (if applicable)
- Force delete: `{module}.{resource}.force-delete` (if applicable)

---

## 3. Controller Convention

```php
<?php

namespace App\Http\Controllers\{Module};

use App\Http\Controllers\Controller;
use App\Http\Requests\{Module}\Store{Model}Request;
use App\Http\Requests\{Module}\Update{Model}Request;
use App\Models\{Model};
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class {Model}Controller extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', {Model}::class);

        $models = {Model}::query()
            ->when($request->search, fn ($q, $search) => $q->where('name', 'ilike', "%{$search}%"))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('{module}/index', [
            'models' => $models,
            'filters' => $request->only(['search']),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', {Model}::class);

        return Inertia::render('{module}/create');
    }

    public function store(Store{Model}Request $request): RedirectResponse
    {
        {Model}::create($request->validated());

        return to_route('{module}.{resource}.index')
            ->with('success', '{Recurso} creado exitosamente.');
    }

    public function show({Model} $model): Response
    {
        Gate::authorize('view', $model);

        return Inertia::render('{module}/show', [
            'model' => $model,
        ]);
    }

    public function edit({Model} $model): Response
    {
        Gate::authorize('update', $model);

        return Inertia::render('{module}/edit', [
            'model' => $model,
        ]);
    }

    public function update(Update{Model}Request $request, {Model} $model): RedirectResponse
    {
        $model->update($request->validated());

        return to_route('{module}.{resource}.index')
            ->with('success', '{Recurso} actualizado exitosamente.');
    }

    public function destroy({Model} $model): RedirectResponse
    {
        Gate::authorize('delete', $model);

        $model->delete();

        return to_route('{module}.{resource}.index')
            ->with('success', '{Recurso} eliminado exitosamente.');
    }
}
```

**Key rules**:

- `Gate::authorize()` is used (not `$this->authorize()`) because the base `Controller` class does not include `AuthorizesRequests`. See ADR-009 §7.
- Flash messages use `->with('success', '…')` or `->with('error', '…')`. These are shared by `HandleInertiaRequests` as lazy closures and rendered by `FlashToaster`.
- Search uses PostgreSQL `ilike` for case-insensitive matching. Chain additional filters with `->when()`.
- Always call `->withQueryString()` on the paginator to preserve filter parameters in pagination links.

### Contextual Creation (Double-Permission Rule)

If a module supports creating a resource in the context of another (e.g., creating an address for a user), the controller MUST check **both** permissions:

```php
public function create(User $user): Response
{
    Gate::authorize('view', $user);          // can the requester access the parent?
    Gate::authorize('create', Address::class); // can the requester create this resource?

    return Inertia::render('users/addresses/create', ['user' => $user]);
}
```

---

## 4. FormRequest Convention

### Store Request

```php
<?php

namespace App\Http\Requests\{Module};

use App\Models\{Model};
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class Store{Model}Request extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', {Model}::class);
    }

    /** @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            // Add module-specific rules here...
        ];
    }
}
```

### Update Request

```php
<?php

namespace App\Http\Requests\{Module};

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class Update{Model}Request extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('update', $this->route('{model}'));
    }

    /** @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            // Add module-specific rules here...
        ];
    }
}
```

**Key rules**:

- `authorize()` in the FormRequest is **Layer 3** of the five-layer security model. It fires before `rules()` validation.
- For `StoreRequest`, authorize against the model **class** (`{Model}::class`). For `UpdateRequest`, authorize against the **route-bound instance** (`$this->route('{model}')`).
- The controller's `Gate::authorize()` call is **Layer 2**. Both layers must be present for mutating actions.

---

## 5. Policy Convention

```php
<?php

namespace App\Policies;

use App\Models\{Model};
use App\Models\User;

class {Model}Policy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('{module}.{resource}.view');
    }

    public function view(User $user, {Model} $model): bool
    {
        return $user->hasPermissionTo('{module}.{resource}.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('{module}.{resource}.create');
    }

    public function update(User $user, {Model} $model): bool
    {
        return $user->hasPermissionTo('{module}.{resource}.edit');
    }

    public function delete(User $user, {Model} $model): bool
    {
        return $user->hasPermissionTo('{module}.{resource}.delete');
    }

    // ─── Lifecycle methods (add only if the module supports them) ─────────────

    public function deactivate(User $user, {Model} $model): bool
    {
        return $user->hasPermissionTo('{module}.{resource}.deactivate');
    }

    public function restore(User $user, {Model} $model): bool
    {
        return $user->hasPermissionTo('{module}.{resource}.restore')
            && $user->hasPermissionTo('{module}.{resource}.view-trashed');
    }

    public function forceDelete(User $user, {Model} $model): bool
    {
        return $user->hasPermissionTo('{module}.{resource}.force-delete');
    }

    public function viewTrashed(User $user): bool
    {
        return $user->hasPermissionTo('{module}.{resource}.view-trashed');
    }
}
```

**Key rules**:

- Policies reference **permissions only** — never role names. See ADR-009 §8.
- Register the policy via auto-discovery (model → policy naming convention) or explicitly in `AuthServiceProvider`.
- All abilities that will be used in the module MUST be seeded in the module's permissions seeder.

---

## 6. Frontend Page Convention

### Controller → FormRequest → Policy → Inertia Convention

The data flow for every CRUD operation follows this chain:

```
Inertia Request
    │
    ▼
Route Middleware (auth + verified + ensure-two-factor)
    │
    ▼
Controller::method()
    ├── Gate::authorize('ability', model)     ← Layer 2
    ├── FormRequest::authorize() → Policy     ← Layer 3 (mutating only)
    ├── FormRequest::rules() → validate       ← Layer 4 (mutating only)
    └── Inertia::render('page', ['prop' => $data]) or to_route()->with('flash')
                                               │
                                               ▼
                                         React Page Component
                                         receives typed props
```

### Index Page (`resources/js/pages/{module}/index.tsx`)

```tsx
import AppLayout from '@/layouts/app-layout'
import { Toolbar, ToolbarGroup } from '@/components/ui/toolbar'
import { Table, TableHeader, TableBody, TableHead, TableRow, TableCell } from '@/components/ui/table'
import { Pagination } from '@/components/ui/pagination'
import { EmptyState } from '@/components/ui/empty-state'
import { ConfirmationDialog } from '@/components/ui/confirmation-dialog'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { useCan } from '@/hooks/use-can'
import { Link, router } from '@inertiajs/react'
import { useState } from 'react'
import { index, destroy } from '@/actions/App/Http/Controllers/{Module}/{Model}Controller'
import { create as createRoute } from '@/routes/{module}'  // or @/actions
import type { PaginatedData, BreadcrumbItem } from '@/types'
import type { {Model} } from '@/types/{module}'

type Props = {
    models: PaginatedData<{Model}>
    filters: { search: string }
    breadcrumbs: BreadcrumbItem[]
}

export default function {Model}Index({ models, filters, breadcrumbs }: Props) {
    const canCreate = useCan('{module}.{resource}.create')
    const canEdit   = useCan('{module}.{resource}.edit')
    const canDelete = useCan('{module}.{resource}.delete')

    const [search, setSearch] = useState(filters.search ?? '')
    const [deleteTarget, setDeleteTarget] = useState<{Model} | null>(null)

    function handleSearch() {
        router.get(index.url(), { search }, { preserveState: true, replace: true })
    }

    function handleDelete() {
        if (!deleteTarget) return
        router.delete(destroy.url(deleteTarget.id), {
            onSuccess: () => setDeleteTarget(null),
        })
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <div className="space-y-4 p-4">
                <Toolbar>
                    <ToolbarGroup>
                        <Input
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            onKeyDown={(e) => e.key === 'Enter' && handleSearch()}
                            placeholder="Buscar..."
                            className="w-64"
                        />
                    </ToolbarGroup>
                    <ToolbarGroup>
                        {canCreate && (
                            <Button asChild>
                                <Link href={createRoute.url()}>Nuevo {Recurso}</Link>
                            </Button>
                        )}
                    </ToolbarGroup>
                </Toolbar>

                {models.data.length === 0 ? (
                    <EmptyState
                        title="Sin {recursos}"
                        description="No hay {recursos} registrados aún."
                        action={canCreate ? <Button asChild><Link href={createRoute.url()}>Crear {recurso}</Link></Button> : undefined}
                    />
                ) : (
                    <>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Nombre</TableHead>
                                    <TableHead className="text-right">Acciones</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {models.data.map((model) => (
                                    <TableRow key={model.id}>
                                        <TableCell>{model.name}</TableCell>
                                        <TableCell className="text-right">
                                            {canEdit && (
                                                <Button variant="ghost" size="sm" asChild>
                                                    {/* Import edit from wayfinder */}
                                                    <Link href={/* edit.url(model.id) */''}>Editar</Link>
                                                </Button>
                                            )}
                                            {canDelete && (
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    className="text-destructive hover:text-destructive"
                                                    onClick={() => setDeleteTarget(model)}
                                                >
                                                    Eliminar
                                                </Button>
                                            )}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>

                        <Pagination links={models.links} />
                    </>
                )}
            </div>

            <ConfirmationDialog
                open={deleteTarget !== null}
                onOpenChange={(open) => !open && setDeleteTarget(null)}
                title="Eliminar {recurso}"
                description={`¿Estás seguro de que deseas eliminar "${deleteTarget?.name}"? Esta acción no se puede deshacer.`}
                onConfirm={handleDelete}
            />
        </AppLayout>
    )
}
```

### Create/Edit Page (`resources/js/pages/{module}/create.tsx`)

```tsx
import AppLayout from '@/layouts/app-layout'
import { {Model}Form } from './components/{model}-form'
import { store } from '@/actions/App/Http/Controllers/{Module}/{Model}Controller'
import type { BreadcrumbItem } from '@/types'

type Props = {
    breadcrumbs: BreadcrumbItem[]
}

export default function {Model}Create({ breadcrumbs }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <div className="max-w-2xl space-y-6 p-4">
                <h1 className="text-2xl font-semibold">Crear {Recurso}</h1>
                <{Model}Form action={store.form()} />
            </div>
        </AppLayout>
    )
}
```

### Shared Form Component (`resources/js/pages/{module}/components/{model}-form.tsx`)

```tsx
import { Form } from '@inertiajs/react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import InputError from '@/components/input-error'
import type { InertiaFormProps } from '@inertiajs/react'

type {Model}FormProps = {
    action: { action: string; method: string }
    defaultValues?: {
        name?: string
    }
}

export function {Model}Form({ action, defaultValues }: {Model}FormProps) {
    return (
        <Form {...action}>
            {({ errors, processing }) => (
                <div className="space-y-4">
                    <div className="space-y-1.5">
                        <Label htmlFor="name">Nombre</Label>
                        <Input
                            id="name"
                            name="name"
                            defaultValue={defaultValues?.name}
                            autoFocus
                        />
                        <InputError message={errors.name} />
                    </div>

                    <Button type="submit" disabled={processing}>
                        {processing ? 'Guardando...' : 'Guardar'}
                    </Button>
                </div>
            )}
        </Form>
    )
}
```

---

## 7. Wayfinder Convention

Wayfinder generates TypeScript functions from Laravel routes. All forms and links in CRUD modules MUST use Wayfinder — **hardcoded `href="/path"` strings are forbidden**.

### Forms

Use `<Form {...Controller.action.form()}>` to bind the form action and method:

```tsx
import { store } from '@/actions/App/Http/Controllers/{Module}/{Model}Controller'
import { Form } from '@inertiajs/react'

// ✅ Correct — Wayfinder-generated action
<Form {...store.form()}>
    {({ errors, processing }) => ( /* fields */ )}
</Form>

// ❌ Forbidden — hardcoded action URL
<Form action="/system/users" method="post">
    {/* ... */}
</Form>
```

### Links and Navigation

Import from `@/actions` (controller-based) or `@/routes` (named routes):

```tsx
import { index, edit } from '@/actions/App/Http/Controllers/{Module}/{Model}Controller'
import { Link } from '@inertiajs/react'

// ✅ Correct — Wayfinder URL
<Link href={index.url()}>Ver lista</Link>
<Link href={edit.url(model.id)}>Editar</Link>

// ❌ Forbidden — hardcoded href
<Link href="/system/users">Ver lista</Link>
<a href="/system/users/1/edit">Editar</a>
```

### Router Programmatic Navigation

```tsx
import { index } from '@/actions/App/Http/Controllers/{Module}/{Model}Controller';
import { router } from '@inertiajs/react';

// ✅ Correct
router.delete(destroy.url(model.id));

// ❌ Forbidden
router.delete(`/system/users/${model.id}`);
```

### Regenerating Wayfinder Files

After any route change, regenerate the Wayfinder modules:

```bash
php artisan wayfinder:generate --with-form --no-interaction
```

The Vite plugin auto-regenerates in dev mode; this command is only needed after changes in non-dev contexts.

---

## 8. Breadcrumb Flow

Breadcrumbs are passed as an Inertia page prop from the controller and consumed by the layout header.

### TypeScript Type

```typescript
// From @/types/navigation
export type BreadcrumbItem = {
    title: string;
    href?: string;
};
```

### Controller

Pass `breadcrumbs` as an Inertia prop:

```php
return Inertia::render('{module}/index', [
    'models'      => $models,
    'filters'     => $request->only(['search']),
    'breadcrumbs' => [
        ['title' => 'Dashboard', 'href' => route('dashboard')],
        ['title' => '{Recursos}'],  // last item has no href — current page
    ],
]);
```

### Frontend Page

Destructure and pass to `AppLayout`:

```tsx
export default function {Model}Index({ models, filters, breadcrumbs }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            {/* page content */}
        </AppLayout>
    )
}
```

### How Breadcrumbs Reach the Header

```
Controller
  → Inertia::render('page', ['breadcrumbs' => [...]])
  → Page Component: <AppLayout breadcrumbs={breadcrumbs}>
  → AppLayout → AppSidebarLayout
  → AppSidebarHeader (receives breadcrumbs prop)
  → Renders <Breadcrumb> component from @/components/ui/breadcrumb
```

The `AppSidebarHeader` component reads the `breadcrumbs` prop from `AppLayoutProps` and renders it using the shared Breadcrumb UI primitive. No global store or context is needed.

---

## 9. Lifecycle Operations

### Soft Delete Pattern

Enable soft deletes on the model:

```php
use Illuminate\Database\Eloquent\SoftDeletes;

class {Model} extends Model
{
    use SoftDeletes;
}
```

The migration must include a `deleted_at` column:

```php
$table->softDeletes();
```

The standard `destroy` controller method soft-deletes automatically when `SoftDeletes` is present.

### Restore Controller

Create a dedicated invokable controller for restore:

```php
<?php

namespace App\Http\Controllers\{Module};

use App\Http\Controllers\Controller;
use App\Models\{Model};
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class Restore{Model}Controller extends Controller
{
    public function __invoke({Model} $model): RedirectResponse
    {
        Gate::authorize('restore', $model);

        $model->restore();

        return to_route('{module}.{resource}.index')
            ->with('success', '{Recurso} restaurado exitosamente.');
    }
}
```

Route (note `->withTrashed()`):

```php
Route::patch('{resource}/{model}/restore', [Restore{Model}Controller::class, '__invoke'])
    ->withTrashed()
    ->name('{resource}.restore');
```

### Force Delete Controller

```php
public function __invoke({Model} $model): RedirectResponse
{
    Gate::authorize('forceDelete', $model);

    $model->forceDelete();

    return to_route('{module}.{resource}.index')
        ->with('success', '{Recurso} eliminado permanentemente.');
}
```

### ConfirmationDialog for Destructive Actions

All destructive operations (destroy, force-delete, deactivate) MUST use `ConfirmationDialog`:

```tsx
import { ConfirmationDialog } from '@/components/ui/confirmation-dialog';
import { router } from '@inertiajs/react';
import { destroy } from '@/actions/App/Http/Controllers/{Module}/{Model}Controller';
import { useState } from 'react';

const [target, setTarget] = useState<{ Model } | null>(null);
const [processing, setProcessing] = useState(false);

function handleDelete() {
    if (!target) return;
    setProcessing(true);
    router.delete(destroy.url(target.id), {
        onSuccess: () => {
            setTarget(null);
            setProcessing(false);
        },
        onError: () => setProcessing(false),
    });
}

<ConfirmationDialog
    open={target !== null}
    onOpenChange={(open) => !open && setTarget(null)}
    title="Eliminar {recurso}"
    description={`¿Eliminar "${target?.name}"? Esta acción no se puede deshacer.`}
    onConfirm={handleDelete}
    loading={processing}
/>;
```

### Viewing Trashed Records

To show soft-deleted records, scope the query with `withTrashed()` or `onlyTrashed()`:

```php
$trashedModels = {Model}::onlyTrashed()->latest()->paginate(15);
```

Protect the route with a dedicated `view-trashed` ability:

```php
Gate::authorize('viewTrashed', {Model}::class);
```

---

## 10. Five-Layer Security Model

Every CRUD action is protected by five independent layers. **All five must be present in every module.**

| Layer                          | Where                                                 | Mechanism                                          | Rejects With   |
| ------------------------------ | ----------------------------------------------------- | -------------------------------------------------- | -------------- |
| **1 — Authentication**         | `routes/{module}.php`                                 | `auth`, `verified`, `ensure-two-factor` middleware | 401 / redirect |
| **2 — Controller gate**        | `{Model}Controller.php`                               | `Gate::authorize('ability', model)`                | 403            |
| **3 — FormRequest authorize**  | `Store{Model}Request.php`, `Update{Model}Request.php` | `Gate::allows()` / `$this->user()->can()`          | 403            |
| **4 — FormRequest validation** | `Store{Model}Request.php`, `Update{Model}Request.php` | `rules()` array                                    | 422            |
| **5 — Frontend useCan**        | `{module}/index.tsx`, `{module}/create.tsx`, etc.     | `useCan('{module}.{resource}.action')`             | UI hidden only |

Layers 1–4 are backend enforcement. Layer 5 is UI convenience. **Never omit layers 1–4 in favor of layer 5.**

```
User request
    ↓
[1] Route middleware: auth + verified + ensure-two-factor
    ↓ (authenticated)
[2] Controller: Gate::authorize('viewAny', Model::class)
    ↓ (authorized)
[3] FormRequest::authorize() → Policy::create($user)         (mutating only)
    ↓ (authorized)
[4] FormRequest::rules() → validated data                    (mutating only)
    ↓ (valid)
    Business logic (create / update / delete)
    ↓
    Redirect with flash → FlashToaster → Sonner toast
```

---

## Checklist A — Module Consistency

Use this checklist before opening a PR for a new or modified CRUD module.

- [ ] Route file created at `routes/{module}.php` and registered in `routes/web.php`
- [ ] Route group uses `['auth', 'verified', 'ensure-two-factor']` middleware
- [ ] Route names follow `{module}.{resource}.{action}` convention
- [ ] Controller uses `Gate::authorize()` on every action (not `$this->authorize()`)
- [ ] `StoreRequest` and `UpdateRequest` exist and both implement `authorize()` + `rules()`
- [ ] Policy registered (auto-discovery or `AuthServiceProvider`)
- [ ] Policy methods reference permissions, never role names
- [ ] Permissions seeded in a dedicated `{Module}PermissionsSeeder`
- [ ] Factory created for the model
- [ ] All flash messages use `->with('success', '…')` / `->with('error', '…')`
- [ ] All Inertia renders pass `breadcrumbs` array prop
- [ ] No hardcoded `href="/path"` strings — all links/forms use Wayfinder
- [ ] `<Form {...action.form()}>` used for all create/edit forms
- [ ] Navigation filtering in `HandleInertiaRequests` updated if new nav links added
- [ ] Permissions visible in `useCan` without additional configuration (auto-shared)
- [ ] All five test files created and passing
- [ ] `vendor/bin/pint --dirty --format agent` run on all PHP files
- [ ] `npm run types:check` passes

---

## Checklist B — Lifecycle Operations

Use this checklist if the module supports soft delete, deactivate, restore, or force-delete.

- [ ] Model uses `SoftDeletes` trait
- [ ] Migration has `$table->softDeletes()`
- [ ] `destroy()` controller action confirmed to call `$model->delete()` (not `forceDelete()`)
- [ ] Restore controller created (`Restore{Model}Controller`) with `Gate::authorize('restore', $model)`
- [ ] Force-delete controller created (`ForceDelete{Model}Controller`) with `Gate::authorize('forceDelete', $model)`
- [ ] Both lifecycle routes use `->withTrashed()` route binding
- [ ] Both lifecycle routes named correctly (`{resource}.restore`, `{resource}.force-delete`)
- [ ] Policy methods `restore()`, `forceDelete()`, `viewTrashed()` implemented
- [ ] All three lifecycle permissions seeded (`{module}.{resource}.restore`, `{module}.{resource}.force-delete`, `{module}.{resource}.view-trashed`)
- [ ] Restore and force-delete actions gated by `<ConfirmationDialog>` in the UI
- [ ] `useCan('{module}.{resource}.restore')` used to conditionally show restore button
- [ ] `useCan('{module}.{resource}.force-delete')` used to conditionally show force-delete button
- [ ] Trashed records accessible only when user has `view-trashed` permission

---

## Checklist C — Soft Delete

A focused checklist for the soft delete requirement specifically (subset of Checklist B).

- [ ] `use SoftDeletes` trait on the model
- [ ] `$table->softDeletes()` in the migration
- [ ] Default `destroy` action calls `$model->delete()` (soft delete)
- [ ] URL route binding does NOT resolve soft-deleted records by default (Laravel default behavior)
- [ ] Restore route explicitly uses `->withTrashed()` so the model can be resolved for restore
- [ ] Force-delete route explicitly uses `->withTrashed()` so the model can be resolved
- [ ] `view-trashed` permission required to access a "trash bin" index or view deleted records
- [ ] `restore` ability covers both `restore` and `view-trashed` permissions in the policy
- [ ] Soft-delete behavior documented in module-level PHPDoc or README if non-obvious

---

## Checklist D — Visual Verification

Perform this manual verification before merging a new or modified CRUD module.

### Light Mode

- [ ] Index table renders correctly (borders, background, text contrast)
- [ ] Empty state renders with icon, title, description, and CTA
- [ ] Confirmation dialog renders with correct variant (destructive = red confirm button)
- [ ] Toolbar search input and action button are aligned and readable
- [ ] Flash success toast appears after a create/update/delete action
- [ ] Flash error toast appears when an error is flashed

### Dark Mode

- [ ] All of the above in dark mode — toggle via appearance menu
- [ ] No hardcoded light-only colors visible (e.g., white backgrounds, black text without `dark:` variant)
- [ ] Table row hover state uses semantic `hover:bg-accent/50` (not `hover:bg-gray-100`)

### Mobile (≥ 320px width)

- [ ] Toolbar stacks vertically (search above actions)
- [ ] Table has horizontal scroll (`overflow-x-auto` wrapper) — no content cut off
- [ ] Pagination shows only prev/next on small screens (page numbers hidden below `sm`)
- [ ] Confirmation dialog is readable and usable at narrow widths (`sm:max-w-md`)
- [ ] All interactive elements have at least 44×44px touch targets
- [ ] Empty state text is legible and not truncated
- [ ] Form fields are full-width and usable on mobile

---

## Pest Test Coverage Expectations

Every CRUD module must have the following Pest test coverage as a minimum. See `ADR-009` for the full rationale.

```
tests/Feature/{Module}/
├── {Model}IndexTest.php         # authorized view, unauthorized 403, search filter, empty response
├── {Model}CreateTest.php        # authorized render, unauthorized 403, valid store, validation errors
├── {Model}UpdateTest.php        # authorized render, unauthorized 403, valid update, validation errors
├── {Model}DeleteTest.php        # soft delete, restore, force-delete (if applicable)
└── {Model}AuthorizationTest.php # all 5 layers × all HTTP verbs
```

Use `RefreshDatabase`, `actingAs()`, and factory-created users with explicit `givePermissionTo()` calls. Seed permissions with `$this->seed(RolesAndPermissionsSeeder::class)` in `beforeEach`.

## Rendering Verification Note (Current Baseline)

This repository currently treats `tests/Feature/Authorization/ComponentContractTest.php` as a pragmatic rendering proxy for shared primitives in the CRUD foundation.

- It verifies component/hook source presence at expected paths.
- It verifies backend shared-prop contracts consumed by frontend primitives (`auth.permissions`, `flash`).
- It verifies production build integrity through `public/build/manifest.json`.

This is a contract/build-level guarantee, not a dedicated React component rendering harness. If you need DOM-level component rendering and interaction assertions, propose a follow-up change to add a React test runner (for example Vitest + React Testing Library) and migrate targeted cases there.
