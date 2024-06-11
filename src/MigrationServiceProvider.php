<?php

namespace Enflow\Component\Laravel;

use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\ServiceProvider;

class MigrationServiceProvider extends ServiceProvider implements DeferrableProvider
{
    public function register()
    {
        $this->app->extend('migration.creator', function ($migrator, $app) {
            return new \Illuminate\Database\Migrations\MigrationCreator($app['files'], __DIR__.'/stubs');
        });

        $this->app->extend('migrator', function ($migrator, $app) {
            return new class($app['migration.repository'], $app['db'], $app['files'], $app['events']) extends Migrator
            {
                public function run($paths = [], array $options = [])
                {
                    $connection = $this->resolveConnection($this->connection);

                    if (app()->environment('local') && !in_array($connection->getConfig('host'), ['127.0.0.1', 'localhost', 'mysql', 'db.clu0.enflow.nl']) && !str_ends_with($connection->getConfig('host'), '.local')) {
                        $this->output->writeln('<error>Unable to migrate: connected to external database source.</error>');

                        return [];
                    }

                    return parent::run($paths, $options);
                }
            };
        });
    }

    public function provides()
    {
        return ['migrator', 'migration.creator'];
    }
}
