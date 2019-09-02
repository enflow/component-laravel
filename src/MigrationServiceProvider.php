<?php

namespace Enflow\Component\Laravel;

use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\ServiceProvider;

class MigrationServiceProvider extends ServiceProvider
{
    protected $defer = true;

    public function register()
    {
        $this->app->singleton('migration.creator', function ($app) {
            return new Migrations\MigrationCreator($app['files']);
        });

        $this->app->singleton('migrator', function ($app) {
            return new class($app['migration.repository'], $app['db'], $app['files'], $app['events']) extends Migrator
            {
                public function run($paths = [], array $options = [])
                {
                    if (app()->environment() === 'local' && !in_array(config('database.connections.mysql.host'), ['127.0.0.1', 'localhost', 'mysql', 'db.clu0.enflow.nl'])) {
                        $this->note('<error>Unable to migrate: connected to external database source.</error>');

                        return [];
                    }

                    return parent::run($paths, $options);
                }
            };
        });
    }

    public function provides()
    {
        return ['migration.creator'];
    }
}
