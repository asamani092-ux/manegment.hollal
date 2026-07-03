# Hollal Management Platform

Laravel ERP — Phase 1: Foundation, Users & Dynamic Roles.

## Stack

- Laravel 13 + Livewire 4
- `spatie/laravel-permission` (teams => false)
- Design System: `ds-*` classes via `public/css/hollal-ds.css` (RTL)

## Setup (after PHP 8.2+ & Composer)

```powershell
cd hollal-platform
composer install
copy .env.example .env
php artisan key:generate

# Configure DB in .env (MySQL or enable pdo_sqlite in php.ini)
php artisan migrate
php artisan db:seed
php artisan serve
```

**Default admin:** جوال `0500000000` / كلمة المرور `password`

## Phase 2 Routes

| Route | Permission |
|-------|------------|
| `/projects` | `projects.view` |
| `/tasks` | `tasks.view` |

## Phase 2 Migrations (after Phase 1)

6. `create_projects_table`
7. `create_project_user_table` (pivot — no soft deletes)
8. `create_partnerships_table`
9. `create_tasks_table`

```powershell
php artisan migrate
php artisan db:seed --class=PermissionSeeder
php artisan db:seed --class=RoleSeeder
```

## Migrations (order)

1. `0001_*_create_users_table` (unchanged)
2. `create_departments_table`
3. `create_permission_tables`
4. `add_soft_deletes_to_roles_table`
5. `add_hr_fields_to_users_table`

**Do not hard-delete** — all models use `SoftDeletes`.

## Seeders

- `PermissionSeeder` — system permissions
- `RoleSeeder` — Super Admin + sync permissions
- `AdminUserSeeder` — default admin user
