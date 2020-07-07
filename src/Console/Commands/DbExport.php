<?php

namespace Enflow\Component\Laravel\Console\Commands;

use Enflow\Component\Laravel\Events\DatabaseExported;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Symfony\Component\Process\Process;

class DbExport extends Command
{
    use CommandHelpers;

    protected $signature = 'db:export {file?}';

    protected $description = 'Export database to local file';

    public function handle()
    {
        $database = config('database.connections.mysql');
        $file = $this->argument('file') ?? $this->ask('Please provide a file name to export to', 'db.sql');

        if (count(Arr::only($database, ['host', 'username', 'password', 'database'])) < 4) {
            $this->error('All database variables are required.');
            return;
        }

        $this->warn('Starting export ' . $database['database']);
        $process = Process::fromShellCommandline($this->buildCommand($database, $file));
        $process->setTimeout(5);
        $this->timeProcess($process);

        event(new DatabaseExported());
        $this->info('Exported to ' . $file);
    }

    private function buildCommand(array $database, string $file)
    {
        $columnStatistics = version_compare($this->mysqlVersion(), '8.0', '>=') ? '--column-statistics=0' : null;
        $flags = "{$columnStatistics} --ssl-mode=REQUIRED --opt --single-transaction --extended-insert --skip-lock-tables --quick --routines --skip-add-locks";
        $auth = "-u{$database['username']} -p{$database['password']} -h{$database['host']} {$database['database']}";
        $ignores = collect(config('database.excluded', []))->map(function (string $table) use ($database) {
            return '--ignore-table=' . $database['database'] . '.' . $table;
        })->implode(' ');

        return "mysqldump {$flags} {$auth} > $file";
    }
}
