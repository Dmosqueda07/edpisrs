<?php

namespace App\Providers;

use App\Database\Connectors\SqlServerConnector;
use Illuminate\Database\SqlServerConnection;
use Illuminate\Support\Facades\DB;
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
