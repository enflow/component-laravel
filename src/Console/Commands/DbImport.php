<?php

namespace Enflow\Component\Laravel\Console\Commands;

use Enflow\Component\Laravel\Events\DatabaseImported;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Symfony\Component\Process\Process;

class DbImport extends Command
{
    use CommandHelpers;

    protected $signature = 'db:import {file?} {--force}';

    protected $description = 'Import database from local file';

    public function handle()
    {
        $database = config('database.connections.mysql');
        if (!$this->option('force') && !$this->confirm("Are you sure you want to import into {$database['database']}, you might lose data")) {
            return;
        }

        if (count(Arr::only($database, ['host', 'username', 'password', 'database'])) < 4) {
            $this->error('All database variables are required.');
            return;
        }

        if (app()->environment() !== 'local') {
            $this->call('down');
        }

        $file = $this->argument('file') ?? $this->ask('Please provide a file name to import from', 'db.sql');
        if (!file_exists($file)) {
            $this->error("File ({$file}) doesn't exist");
            return;
        }
        $this->warn('Starting import ' . $database['database'] . ' from ' . $file);

        file_put_contents('/tmp/mysql-import-before', 'SET autocommit=0;SET unique_checks=0;SET foreign_key_checks=0;');
        file_put_contents('/tmp/mysql-import-after', 'COMMIT; SET unique_checks=1; SET foreign_key_checks=1;');

        $process = Process::fromShellCommandline($this->buildCommand($database, $file));
        $process->setTimeout(5);
        $this->timeProcess($process);

        event(new DatabaseImported());

        if (app()->environment() !== 'local') {
            $this->call('up');
        }
        $this->info('Imported');
    }

    private function buildCommand(array $database, string $file)
    {
        $auth = "-u{$database['username']} -p{$database['password']} -h{$database['host']} {$database['database']}";
        $flags = "--default-character-set=utf8";

        return "mysql {$flags} {$auth} < {$file}";
    }
}
