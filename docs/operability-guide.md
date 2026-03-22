# Operability Guide

This guide explains how to use the boilerplate's cross-cutting operability layer when building new modules. It covers logging decisions, auditing patterns, exception handling, storage conventions, and queue criteria.

---

## 1. When to throw, log, or audit

| Situation | Action |
|-----------|--------|
| Expected business rule violation (e.g., duplicate submission) | Throw a `BoilerplateException` subclass |
| Unexpected technical failure (e.g., third-party API timeout) | `Log::warning()` + throw if unrecoverable |
| Security event (login, logout, role change, 2FA) | Call `SecurityAuditService::record()` |
| Model field change (e.g., user name updated) | Handled automatically by `laravel-auditing` |
| Unrecoverable system error | `Log::error()` via the exception handler |

**DO NOT** call `abort()` for business rule violations in new modules. Use typed exceptions.

**DO NOT** use `Log::emergency()` for business errors. Reserve it for system-level alerts.

---

## 2. How to add a new Auditable model

1. Add the `Auditable` interface and trait to your model:

```php
use OwenIt\Auditing\Contracts\Auditable;

class Article extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $auditExclude = [
        // List any sensitive fields specific to this model
    ];
}
```

2. Sensitive fields must be excluded via `$auditExclude`. Do not rely solely on `config/audit.php` — defense-in-depth requires both.

3. Never use `DB::table()` or raw SQL to update auditable models — changes made outside Eloquent bypass the audit observers.

4. If auditing needs to be active in tests, add to `beforeEach`:

```php
config(['audit.console' => true]);
ModelName::observe(\OwenIt\Auditing\AuditableObserver::class);
```

---

## 3. How to extend Spatie models (Role, Permission)

The boilerplate provides `App\Models\Role` and `App\Models\Permission` as local subclasses of the Spatie models. Always import from `App\Models\*`, never from `Spatie\Permission\Models\*` directly:

```php
// ✅ Correct
use App\Models\Role;
use App\Models\Permission;

// ❌ Incorrect
use Spatie\Permission\Models\Role;
```

This indirection allows adding Auditable, custom scopes, or business methods without patching vendor code.

---

## 4. How to add a new security event type

1. Add the new event to `App\Enums\SecurityEventType`:

```php
case NewEvent = 'new_event';
```

2. Create a listener or observer that calls `SecurityAuditService::record()`:

```php
$this->auditService->record(
    SecurityEventType::NewEvent,
    $userId,
    request()->ip(),
    ['extra' => 'context'],
);
```

3. Register the listener in `AppServiceProvider::configureAuditing()`.

4. Write a test that verifies the `security_audit_log` row is created with the correct `event_type`.

**Never** write directly to `security_audit_log` table outside of `SecurityAuditService`. The service is the single write path — it writes the DB row AND logs to the `security` channel atomically.

---

## 5. Queue criteria checklist

Before dispatching an operation as a queued job, confirm:

- [ ] The operation takes > 200ms OR must be retried on failure.
- [ ] The job class implements `ShouldQueue`.
- [ ] The job has explicit `$tries`, `$backoff`, and `$timeout` properties.
- [ ] The job does not depend on state that changes between dispatch and execution.
- [ ] If dispatched from an HTTP context, the `correlation_id` propagates automatically.
- [ ] If dispatched from a console command, the job calls `Context::add('correlation_id', ...)` in `handle()`.

---

## 6. Storage: `Storage::disk()` rule

All file operations MUST use Laravel's Storage facade with a named disk:

```php
// ✅ Correct
Storage::disk('s3')->put('reports/export.csv', $contents);
Storage::disk('local')->get('temp/file.txt');

// ❌ Prohibited
file_put_contents('/var/www/storage/reports/export.csv', $contents);
fopen('/absolute/path', 'w');
```

New disks (e.g., `exports`, `media`) MUST be defined in `config/filesystems.php` using environment variables. Never hardcode bucket names or paths in module code.

### MinIO vs. AWS S3

Local development uses MinIO (S3-compatible). The only difference between MinIO and real AWS S3 is:

- MinIO: `AWS_USE_PATH_STYLE_ENDPOINT=true`, `AWS_ENDPOINT=http://minio:9000`
- AWS S3: `AWS_USE_PATH_STYLE_ENDPOINT=false`, no `AWS_ENDPOINT`

No code changes are needed to switch — only environment variable changes.

---

## 7. Prohibitions summary

| Prohibited | Correct alternative |
|-----------|---------------------|
| `abort(422, 'Business error')` | `throw new MyException(...)` (BoilerplateException subclass) |
| `env()` outside config files | `config('key')` |
| `Log::shareContext()` for correlation | `Context::add()` (propagates to jobs) |
| Raw SQL on auditable models | Eloquent model methods |
| Direct writes to `security_audit_log` | `SecurityAuditService::record()` |
| Hardcoded file paths | `Storage::disk('name')->put(...)` |
| Jobs without `$tries`/`$backoff`/`$timeout` | Explicit retry policy on every job |
| `Spatie\Permission\Models\Role` import | `App\Models\Role` |
