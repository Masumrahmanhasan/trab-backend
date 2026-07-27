# Laravel Roles & Permissions System — Project Context

A custom roles/permissions system for a Laravel app, built in the style of
`spatie/laravel-permission` but implemented from scratch (no package
dependency). Team/multi-tenant support is intentionally **not** included yet.

## Design decisions & reasoning

- **`key` column (unique) on both `roles` and `permissions`** — the
  system-defined identifier used in code (e.g. `users.edit`,
  `super-admin`). `name` is just the human-readable label.
- **Polymorphic pivot tables** (`model_has_roles`, `model_has_permissions`)
  using `$table->morphs('model')` — lets any model (not just `User`) hold
  roles/permissions later, without a schema change.
- **`id()` + `unique()` instead of composite primary keys** on all three
  pivot tables (`model_has_permissions`, `model_has_roles`,
  `role_has_permissions`) — SQLite doesn't allow two primary keys on one
  table, so each pivot has a normal auto-increment `id` and a `unique()`
  constraint enforces "no duplicate assignment" instead.
- **`foreignIdFor(Model::class)`** used instead of `foreignId('x_id')`
  everywhere, so column names stay in sync if a model class is ever
  renamed.
- **Two separate traits** (`HasRoles`, `HasPermissions`) instead of one
  combined trait — a model can use either independently, or both together
  (in which case `hasPermissionTo()` also checks permissions inherited via
  roles, detected through `method_exists($this, 'roles')`).
- **`Role` uses `HasPermissions` too**, but overrides its `permissions()`
  method. PHP lets a class's own method override a trait method of the
  same name — so `Role::permissions()` returns a `belongsToMany` against
  `role_has_permissions`, while every other trait method
  (`givePermissionTo`, `syncPermissions`, `hasPermissionTo`, ...) keeps
  working automatically since they all call `$this->permissions()`
  internally.
  - **⚠️ Known gotcha**: if this override method is ever accidentally
    removed from `Role.php`, PHP silently falls back to the trait's
    `morphToMany` version (writing to `model_has_permissions` instead of
    `role_has_permissions`) with no error. Always verify with:
    ```php
    $role->permissions()->getTable(); // must return "role_has_permissions"
    ```
- **`config/permissions.php`** drives the `PermissionSeeder` — resources
  and their CRUD actions (`users.view`, `roles.edit`, etc.) are declared in
  config, so adding a new feature's permissions later is just one line in
  config, no seeder code changes.
- **Considered but rejected**: a `models` config section (mapping
  `role`/`permission`/`user` to configurable class strings, Spatie-style)
  for swappable model classes. Decided it was unneeded indirection since
  there's no current plan to swap `Role`/`Permission` for custom
  subclasses — reverted back to hardcoded `Role::class`/`Permission::class`
  in the traits.
- **Seeders use `upsert()` / transactions for performance**, not
  `firstOrCreate()` in a loop — fewer queries, and avoids SQLite's
  per-statement fsync overhead (a real observed slowdown, especially on
  Windows/Herd setups) by wrapping `RoleSeeder` in `DB::transaction()`.
- **Default `user` role auto-assigned via `User::booted()` +
  `static::created()`**, not tied to a specific registration controller —
  works no matter which flow creates the user (Breeze, Fortify, custom,
  admin panel, seeder, etc.).

## Seeding order matters

`RoleSeeder` grants Super Admin *every permission that exists at the time
it runs* — so `PermissionSeeder` must run first. `DatabaseSeeder` enforces
this:
```php
$this->call([
    PermissionSeeder::class,
    RoleSeeder::class,
]);
```
Never run `php artisan db:seed --class=RoleSeeder` in isolation on a fresh
DB — it'll create Super Admin with zero permissions attached.

## File-by-file listing

### `database/migrations/2026_07_25_000000_create_permission_tables.php`
```php
<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $teams = false; // team support disabled for now

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('key')->unique();
            $table->string('guard_name');
            $table->timestamps();
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('key')->unique();
            $table->string('guard_name');
            $table->timestamps();
        });

        Schema::create('model_has_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Permission::class)->constrained()->cascadeOnDelete();
            $table->morphs('model');

            $table->unique(['permission_id', 'model_id', 'model_type']);
        });

        Schema::create('model_has_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Role::class)->constrained()->cascadeOnDelete();
            $table->morphs('model');

            $table->unique(['role_id', 'model_id', 'model_type']);
        });

        Schema::create('role_has_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Permission::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(Role::class)->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['permission_id', 'role_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_has_permissions');
        Schema::dropIfExists('model_has_roles');
        Schema::dropIfExists('model_has_permissions');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('permissions');
    }
};
```

### `app/Models/Permission.php`
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class Permission extends Model
{
    protected $fillable = [
        'name',
        'key',
        'guard_name',
    ];

    /**
     * Roles that have this permission.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_has_permissions');
    }

    /**
     * Users (or any model) directly assigned this permission.
     */
    public function users(): MorphToMany
    {
        return $this->morphedByMany(
            User::class,
            'model',
            'model_has_permissions',
            'permission_id',
            'model_id'
        );
    }
}
```

### `app/Models/Role.php`
```php
<?php

namespace App\Models;

use App\Models\Concerns\HasPermissions;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * @property-read int $id
 * @property-read string $name
 * @property-read string $key
 */
#[Fillable(['name', 'key'])]
class Role extends Model
{
    use HasPermissions;

    /**
     * Permissions assigned to this role.
     *
     * Overrides HasPermissions::permissions(), since a role's permissions
     * live in the plain role_has_permissions pivot, not the polymorphic
     * model_has_permissions table the trait defaults to. All of the
     * trait's other methods (givePermissionTo, syncPermissions,
     * hasPermissionTo, ...) call $this->permissions() internally, so they
     * automatically use this override too.
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_has_permissions');
    }

    public function users(): MorphToMany
    {
        return $this->morphedByMany(
            User::class,
            'model',
            'model_has_roles',
            'role_id',
            'model_id',
        );
    }
}
```

### `app/Models/User.php`
```php
<?php

namespace App\Models;

use App\Models\Concerns\HasPermissions;
use App\Models\Concerns\HasRoles;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

/**
 * @property-read int $id
 * @property-read string $name
 * @property-read string $email
 * @property-read string $password
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */

    use HasFactory, Notifiable, HasApiTokens, HasPermissions, HasRoles;

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
        ];
    }

    /**
     * Automatically assign the basic "user" role to every new User,
     * regardless of which registration flow created them (Breeze,
     * Fortify, a custom controller, admin panel, etc.).
     */
    protected static function booted(): void
    {
        static::created(function (User $user) {
            $user->assignRole('user');
        });
    }
}
```

### `app/Models/Concerns/HasRoles.php`
```php
<?php

namespace App\Models\Concerns;

use App\Models\Role;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Collection as SupportCollection;

/**
 * @mixin \Illuminate\Database\Eloquent\Model
 *
 * @property-read Collection<int, Role> $roles
 */
trait HasRoles
{
    /**
     * Roles assigned to this model.
     */
    public function roles(): MorphToMany
    {
        return $this->morphToMany(
            Role::class,
            'model',
            'model_has_roles',
            'model_id',
            'role_id'
        );
    }

    /**
     * Assign one role to the model without removing existing roles.
     */
    public function assignRole(string|Role $role): static
    {
        $this->roles()->syncWithoutDetaching(
            $this->resolveRole($role)
        );

        return $this;
    }

    /**
     * Remove a role from the model.
     */
    public function removeRole(string|Role $role): static
    {
        $this->roles()->detach(
            $this->resolveRole($role)
        );

        return $this;
    }

    /**
     * Replace all of the model's roles with the given set.
     */
    public function syncRoles(array|SupportCollection $roles): static
    {
        $roleIds = collect($roles)->map(
            fn (string|Role $role) => $this->resolveRole($role)->id
        );

        $this->roles()->sync($roleIds);

        return $this;
    }

    /**
     * Determine if the model has the given role.
     */
    public function hasRole(string|Role $role): bool
    {
        $key = $role instanceof Role ? $role->key : $role;

        return $this->roles->contains('key', $key);
    }

    /**
     * Determine if the model has any of the given roles.
     */
    public function hasAnyRole(array $roles): bool
    {
        return collect($roles)->some(fn ($role) => $this->hasRole($role));
    }

    /**
     * Determine if the model has all of the given roles.
     */
    public function hasAllRoles(array $roles): bool
    {
        return collect($roles)->every(fn ($role) => $this->hasRole($role));
    }

    /**
     * Resolve a role key (or instance) into a Role model.
     */
    protected function resolveRole(string|Role $role): Role
    {
        return $role instanceof Role
            ? $role
            : Role::where('key', $role)->firstOrFail();
    }
}
```

### `app/Models/Concerns/HasPermissions.php`
```php
<?php

namespace App\Models\Concerns;

use App\Models\Permission;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Collection as SupportCollection;

/**
 * @mixin \Illuminate\Database\Eloquent\Model
 *
 * @property-read Collection<int, Permission> $permissions
 * @property-read Collection<int, \App\Models\Role> $roles Only present when the model also uses HasRoles.
 */
trait HasPermissions
{
    /**
     * Permissions assigned directly to this model.
     */
    public function permissions(): MorphToMany
    {
        return $this->morphToMany(
            Permission::class,
            'model',
            'model_has_permissions',
            'model_id',
            'permission_id'
        );
    }

    /**
     * Give the model direct access to a permission, without removing existing ones.
     */
    public function givePermissionTo(string|Permission $permission): static
    {
        $this->permissions()->syncWithoutDetaching(
            $this->resolvePermission($permission)
        );

        return $this;
    }

    /**
     * Revoke a directly assigned permission from the model.
     */
    public function revokePermissionTo(string|Permission $permission): static
    {
        $this->permissions()->detach(
            $this->resolvePermission($permission)
        );

        return $this;
    }

    /**
     * Replace all of the model's direct permissions with the given set.
     */
    public function syncPermissions(array|SupportCollection $permissions): static
    {
        $permissionIds = collect($permissions)->map(
            fn (string|Permission $permission) => $this->resolvePermission($permission)->id
        );

        $this->permissions()->sync($permissionIds);

        return $this;
    }

    /**
     * Determine if the model has the given permission, either directly
     * or through one of its roles (if the model also uses HasRoles).
     */
    public function hasPermissionTo(string|Permission $permission): bool
    {
        $key = $permission instanceof Permission ? $permission->key : $permission;

        if ($this->permissions->contains('key', $key)) {
            return true;
        }

        if (method_exists($this, 'roles')) {
            return $this->roles
                ->loadMissing('permissions')
                ->pluck('permissions')
                ->flatten()
                ->contains('key', $key);
        }

        return false;
    }

    /**
     * Determine if the model has any of the given permissions.
     */
    public function hasAnyPermission(array $permissions): bool
    {
        return collect($permissions)->some(fn ($permission) => $this->hasPermissionTo($permission));
    }

    /**
     * Determine if the model has all of the given permissions.
     */
    public function hasAllPermissions(array $permissions): bool
    {
        return collect($permissions)->every(fn ($permission) => $this->hasPermissionTo($permission));
    }

    /**
     * Resolve a permission key (or instance) into a Permission model.
     */
    protected function resolvePermission(string|Permission $permission): Permission
    {
        return $permission instanceof Permission
            ? $permission
            : Permission::where('key', $permission)->firstOrFail();
    }
}
```

### `config/permissions.php`
```php
<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Resource Permissions
    |--------------------------------------------------------------------------
    |
    | Each key is a "resource" (a feature/module). The listed actions are
    | combined to auto-generate permission keys, e.g. "orders" + "view"
    | becomes "orders.view".
    |
    | When you build a new feature later, just add its resource here —
    | no need to touch the seeder.
    |
    */
    'resources' => [
        'users' => ['view', 'create', 'edit', 'delete'],
        'roles' => ['view', 'create', 'edit', 'delete'],
        'permissions' => ['view', 'create', 'edit', 'delete'],

        // Add future features here, e.g.:
        // 'orders' => ['view', 'create', 'edit', 'delete', 'export'],
        // 'invoices' => ['view', 'create', 'edit', 'delete', 'send'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Extra / One-off Permissions
    |--------------------------------------------------------------------------
    |
    | Permissions that don't fit the resource/action pattern above.
    | Key is the permission key, value is the display name.
    |
    */
    'extra' => [
        'roles.assign' => 'Assign Roles',
        'permissions.assign' => 'Assign Permissions',

        // 'invoices.approve' => 'Approve Invoices',
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Guard
    |--------------------------------------------------------------------------
    */
    'guard' => 'web',

];
```

### `database/seeders/PermissionSeeder.php`
```php
<?php

namespace Database\Seeders;

use App\Models\Permission;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * Reads permission definitions from config/permissions.php, so that
     * adding permissions for a future feature never requires touching
     * this seeder — just add an entry to the config file.
     *
     * Uses a single upsert() instead of looping firstOrCreate(), so the
     * whole batch is written in one query no matter how many permissions
     * config/permissions.php ends up defining.
     */
    public function run(): void
    {
        $guard = config('permissions.guard', 'web');
        $now = CarbonImmutable::now();
        $rows = [];

        foreach (config('permissions.resources', []) as $resource => $actions) {
            foreach ($actions as $action) {
                $rows[] = [
                    'key' => "{$resource}.{$action}",
                    'name' => ucfirst($action).' '.ucfirst($resource),
                    'guard_name' => $guard,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        foreach (config('permissions.extra', []) as $key => $name) {
            $rows[] = [
                'key' => $key,
                'name' => $name,
                'guard_name' => $guard,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // Matches on the unique "key" column: inserts new permissions,
        // updates name/guard_name on existing ones, leaves everything else untouched.
        Permission::upsert($rows, uniqueBy: ['key'], update: ['name', 'guard_name', 'updated_at']);
    }
}
```

### `database/seeders/RoleSeeder.php`
```php
<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleSeeder extends Seeder
{
    /**
     * Creates the Super Admin role (with every permission) and a
     * basic User role (no elevated permissions — the default role
     * assigned to anyone who registers).
     *
     * @throws \Throwable if the transaction fails and rolls back —
     *                     intentionally left unhandled so Artisan
     *                     surfaces seeding failures instead of silently swallowing them.
     */
    public function run(): void
    {
        DB::transaction(function () {
            $guard = config('permissions.guard', 'web');

            $superAdmin = Role::firstOrCreate(
                ['key' => 'super-admin'],
                [
                    'name' => 'Super Admin',
                    'guard_name' => $guard,
                ]
            );

            // Uses HasPermissions::syncPermissions() from the trait, which
            // internally calls Role's own permissions() override — so this
            // writes to role_has_permissions, not model_has_permissions.
            $superAdmin->syncPermissions(Permission::all());

            // Basic role for regular registered users. Starts with no
            // permissions — grant specific ones here as your features
            // define what a normal user is allowed to do.
            Role::firstOrCreate(
                ['key' => 'user'],
                [
                    'name' => 'User',
                    'guard_name' => $guard,
                ]
            );
        });
    }
}
```

### `database/seeders/DatabaseSeeder.php`
```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Order matters: PermissionSeeder must run first so that
     * RoleSeeder has permissions available to attach to Super Admin.
     */
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
            RoleSeeder::class,
        ]);
    }
}
```

## Not yet built (possible next steps)

- Middleware / Gate integration (e.g. `can:users.edit` on routes)
- Blade directives (`@role`, `@permission`) if using Blade views
- Admin UI for managing roles/permissions
- API resource/controller for role & permission management
- Tests for `HasRoles` / `HasPermissions` trait behavior
- Team/multi-tenant support (explicitly deferred at the start of this project)
