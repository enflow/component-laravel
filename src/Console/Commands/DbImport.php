<?php

namespace Enflow\Component\Laravel\Console\Commands;

use Enflow\Component\Laravel\Console\Domains\Commands\CommandHelpers;
use Enflow\Component\Laravel\Events\DatabaseImported;
use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class DbImport extends Command
{
    use CommandHelpers;

    protected $signature = 'db:import {file?} {--force}';

    protected $description = 'Import database from local file';

    public function handle()
    {
        $hostname = config('database.connections.mysql.host');
        $username = config('database.connections.mysql.username');
        $password = config('database.connections.mysql.password');
        $database = config('database.connections.mysql.database');

        if (!$this->option('force') && !$this->confirm("Are you sure you want to import into {$database}, you might lose data")) {
            return;
        }

        if (empty($hostname) || empty($username) || empty($password) || empty($database)) {
            $this->error('All database variables are required.');
            return;
        }

        if (app()->environment() !== 'local') {
            $this->call('down');
        }

        $file = $this->argument('file') ?? 'db.sql';
        if (!file_exists($file)) {
            $this->error("File ({$file}) doesn't exist");
            return;
        }
        $this->info('Importing ' . $database . ' from ' . $file);

        file_put_contents('/tmp/mysql-import-before', 'SET autocommit=0;SET unique_checks=0;SET foreign_key_checks=0;');
        file_put_contents('/tmp/mysql-import-after', 'COMMIT; SET unique_checks=1; SET foreign_key_checks=1;');

        $command = "cat /tmp/mysql-import-before {$file} /tmp/mysql-import-after | mysql -u{$username} -p{$password} -h{$hostname} {$database}";
        $process = Process::fromShellCommandline($command);
        $process->setTimeout(5);
        $this->timeProcess($process);

        $this->info('Imported');
        event(new DatabaseImported());

        if (app()->environment() !== 'local') {
            $this->call('up');
        }
        $this->info('Done!');
    }
}
