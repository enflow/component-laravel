<?php

namespace Enflow\Component\Laravel\Console\Commands;

use Enflow\Component\Laravel\Events\DatabaseImported;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Symfony\Component\Process\Process;

class DbImport extends Command
{
    use CommandHelpers;

    protected $signature = 'db:import {file?} {--force} {--skip-maintenance-mode}';

    protected $description = 'Import database from local file';

    public function handle()
    {
        $connection = config('database.connections.mysql');

        if (! $this->option('force') && ! $this->confirm("Are you sure you want to import into {$connection['database']}? You might lose data.")) {
            return;
        }

        if (count(Arr::only($connection, ['host', 'username', 'password', 'database'])) < 4) {
            $this->error('All database variables are required.');
            return;
        }

        if (! $this->option('skip-maintenance-mode') && app()->environment() !== 'local') {
            $this->call('down');
        }

        $file = $this->argument('file') ?? $this->ask('Please provide a file name to import from', 'db.sql');
        if (! file_exists($file)) {
            $this->error("File '{$file}' doesn't exist.");

            return;
        }

        $this->warn('Starting import: ' . $file . ' -> ' . $connection['database']);

        $this->timeProcess(
            Process::fromShellCommandline($this->buildCommand($connection, $file))->setTimeout(5)
        );

        event(new DatabaseImported());

        if (! $this->option('skip-maintenance-mode') && app()->environment() !== 'local') {
            $this->call('up');
        }

        $this->info("Imported {$file}");
    }

    private function buildCommand(array $connection, string $file)
    {
        $auth = "-u{$connection['username']} -p{$connection['password']} -h{$connection['host']} {$connection['database']}";

        file_put_contents('/tmp/mysql-import-before', 'SET autocommit=0;SET unique_checks=0;SET foreign_key_checks=0;');
        file_put_contents('/tmp/mysql-import-after', 'COMMIT; SET unique_checks=1; SET foreign_key_checks=1;');

        return "cat /tmp/mysql-import-before {$file} /tmp/mysql-import-after | mysql {$auth}";
    }
}
