<?php

namespace App\Providers;

use App\Database\Connectors\SqlServerConnector;
use Illuminate\Database\SqlServerConnection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use App\Enums\Role;
use App\Enums\UserStatus;
use Illuminate\Support\ServiceProvider;

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
        Gate::define('viewReports', fn ($user): bool =>
            $user->status === UserStatus::Active
            && $user->is_active
            && in_array($user->role, [Role::Administrator, Role::SuperAdministrator], true)
        );
        Gate::define('viewActivityLogs', fn ($user): bool =>
            $user->status === UserStatus::Active
            && $user->is_active
            && in_array($user->role, [Role::Administrator, Role::SuperAdministrator], true)
        );
        Gate::define('viewAccountActivityLogs', fn ($user): bool =>
            $user->status === UserStatus::Active
            && $user->is_active
            && $user->role === Role::SuperAdministrator
        );

        DB::extend('sqlsrv', function (array $config, string $name) {
            $connector = new SqlServerConnector;
            $config['name'] = $name;

            return new SqlServerConnection(
                $connector->connect($config),
                $config['database'],
                $config['prefix'],
                $config,
            );
        });
    }
}
