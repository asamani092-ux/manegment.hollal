# Security Review — Hollal Platform

**Date:** 2026-07-03  
**Scope:** Laravel 13 + Livewire 4 — `hollal-platform`

---

## 1. Livewire Authorization (IDOR)

### Components Audited (17)

| Component | Model-scoped `$this->authorize()` | Status |
|-----------|-----------------------------------|--------|
| `TasksIndex` | Task policy on edit/delete/status | OK |
| `ExpensesIndex` | ExpenseRequest policy on all mutations | OK |
| `ProjectsIndex` | Project/Partnership policies | Fixed: viewOnly guard on save |
| `ProjectShow` | Project view + submitUpdate | OK |
| `MeetingsIndex` | Meeting policy | OK |
| `MeetingMinutes` | Meeting policy on items | Fixed: authorize on openItemView |
| `OpenDecisionsIndex` | Permission gate only (read-only) | OK |
| `UsersIndex` | User policy (new) | Fixed |
| `DepartmentsIndex` | Department policy (new) | Fixed |
| `RolesIndex` | Role policy (new) | Fixed |
| `DocumentsIndex` | Document policy | OK |
| `ContractsIndex` | Contract policy | OK |
| `PayrollIndex` | Payroll policy | OK |
| `ReportsIndex` | WeeklyReport policy | OK |
| `DashboardIndex` | Permission gate (read-only) | OK |
| `NotificationBell` | Scoped to `auth()->user()->notifications()` | OK |
| `PartnershipGuestView` | Magic-link token validation (public by design) | OK |

### Fixes Applied

- Added `UserPolicy`, `DepartmentPolicy`, `RolePolicy` with model-instance authorization
- Updated `UsersIndex`, `DepartmentsIndex`, `RolesIndex` to call `$this->authorize('action', $model)`
- Added `projectViewOnly` / `partnershipViewOnly` early-return in `ProjectsIndex::save*`
- Added `$this->authorize('view', $this->meeting)` in `MeetingMinutes::openItemView`

### IDOR Tests

`tests/Feature/LivewireIdorTest.php` — employee with view-only permissions attempts cross-user mutations → expects 403.

Existing: `tests/Feature/HorizontalAccessTest.php` (tasks/projects).

---

## 2. Blade XSS Audit

| Location | Finding | Action |
|----------|---------|--------|
| `resources/views/**/*.blade.php` | No `{!! !!}` unescaped output | Clean |
| Livewire compiled layout (`storage/framework/views/`) | `{!! $content !!}` | **Justified** — Livewire internal slot rendering |

All user-facing templates use `{{ }}` escaping.

---

## 3. File Storage Audit

| Upload Path | Disk | Download Route | Policy |
|-------------|------|----------------|--------|
| Tasks | `local` | `/files/tasks/{task}/{type}` | TaskPolicy::downloadFile |
| Expenses | `local` | `/files/expenses/{expenseRequest}` | ExpenseRequestPolicy::downloadAttachment |
| Documents | `local` | `/files/documents/{document}` | DocumentPolicy::download |
| Contracts | `local` | `/files/contracts/{contract}` | ContractPolicy::downloadFile |
| Partnership `contract_pdf` | URL string (external link) | N/A — user-provided URL, not stored file | Documented |

No `public` disk usage for sensitive uploads. `MigrateTaskFilesToLocalCommand` migrates legacy public files.

### Download Tests Added

- `tests/Feature/ExpenseFileDownloadTest.php`
- `tests/Feature/DocumentDownloadTest.php`
- `tests/Feature/ContractFileDownloadTest.php`

---

## 4. Security Headers

**New:** `App\Http\Middleware\SecurityHeadersMiddleware`

| Header | Value |
|--------|-------|
| `Strict-Transport-Security` | `max-age=31536000; includeSubDomains` (production only) |
| `X-Frame-Options` | `DENY` |
| `X-Content-Type-Options` | `nosniff` |
| `Referrer-Policy` | `strict-origin-when-cross-origin` |
| `X-XSS-Protection` | `0` |
| `Permissions-Policy` | `camera=(), microphone=(), geolocation=()` |

Registered globally in `bootstrap/app.php`.

Test: `tests/Feature/SecurityHeadersTest.php`

---

## 5. Session Hardening

| Control | Status |
|---------|--------|
| `SESSION_LIFETIME=60` in `.env.example` | Applied |
| `session()->regenerate()` on login | Already in `AuthController` |
| Login session regeneration test | Added to `AuthTest` |

---

## 6. Production Environment

**New:** `.env.production.example`

- `APP_DEBUG=false`
- MySQL placeholders
- `SESSION_ENCRYPT=true`
- `APP_URL=https://...`

**New:** `AppServiceProvider` — `URL::forceScheme('https')` when `APP_ENV=production`

---

## 7. Remaining Recommendations

1. Rate-limit file download routes if abuse detected
2. Consider CSP header once inline scripts are inventory-complete
3. Run `php artisan migrate:task-files-to-local` once if legacy public task files exist
4. Rotate `APP_KEY` only via planned maintenance (invalidates sessions)

---

## Test Files Added/Updated

| File | Purpose |
|------|---------|
| `tests/Feature/LivewireIdorTest.php` | Cross-user Livewire IDOR |
| `tests/Feature/ExpenseFileDownloadTest.php` | Expense attachment access |
| `tests/Feature/DocumentDownloadTest.php` | Document confidentiality |
| `tests/Feature/ContractFileDownloadTest.php` | Contract file access |
| `tests/Feature/SecurityHeadersTest.php` | HTTP security headers |
| `tests/Feature/AuthTest.php` | Session regeneration on login |
