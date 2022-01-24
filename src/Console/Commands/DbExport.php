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

    protected $description = 'Exports a database to local file';

    public function handle()
    {
        $file = $this->argument('file') ?? $this->ask('Please provide a file name to export to', 'db.sql');

        $connection = config('database.connections.mysql');
        if (count(Arr::only($connection, ['host', 'username', 'password', 'database'])) < 4) {
            $this->error('All database variables are required.');
            return;
        }

        $this->warn('Starting export ' . $connection['database']);

        $this->timeProcess(
            Process::fromShellCommandline($this->buildCommand($connection, $file))->setTimeout(5)
        );

        event(new DatabaseExported());

        $this->info('Exported to ' . $file);
    }

    private function buildCommand(array $connection, string $file)
    {
        $columnStatistics = version_compare($this->mysqlVersion(), '8.0', '>=') ? '--column-statistics=0' : null;

        $flags = "{$columnStatistics} --ssl-mode=REQUIRED --opt --single-transaction --extended-insert --skip-lock-tables --quick --routines --skip-add-locks";

        $auth = "-u{$connection['username']} -p{$connection['password']} -h{$connection['host']} {$connection['database']}";

        return "mysqldump {$flags} {$auth} > $file";
    }
}
