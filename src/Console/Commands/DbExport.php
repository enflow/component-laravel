<?php

namespace Enflow\Component\Laravel\Console\Commands;

use Enflow\Component\Laravel\Console\Domains\Commands\CommandHelpers;
use Enflow\Component\Laravel\Events\DatabaseExported;
use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class DbExport extends Command
{
    use CommandHelpers;

    protected $signature = 'db:export {file?}';

    protected $description = 'Export database to local file';

    public function handle()
    {
        $hostname = config('database.connections.mysql.host');
        $username = config('database.connections.mysql.username');
        $password = config('database.connections.mysql.password');
        $database = config('database.connections.mysql.database');

        if (empty($hostname) || empty($username) || empty($password) || empty($database)) {
            $this->error('All database variables are required.');
            return;
        }

        $columnStatistics = version_compare($this->mysqlVersion(), '8.0', '>=') ? '--column-statistics=0' : null;
        $flags = "{$columnStatistics} --ssl-mode=REQUIRED --opt --single-transaction --extended-insert --skip-lock-tables --quick --routines --skip-add-locks";
        $auth = "-u{$username} -p{$password} -h{$hostname} {$database}";
        $ignores = collect(config('database.excluded', []))->map(function (string $table) use ($database) {
            return '--ignore-table=' . $database . '.' . $table;
        })->implode(' ');
        $file = $this->argument('file') ?? 'db.sql';
        $this->info('Exporting ' . $database);

        $command = "mysqldump {$flags} {$auth} > $file";

        $process = Process::fromShellCommandline($command);
        $process->setTimeout(5);
        $this->timeProcess($process);

        $this->info('Exported to ' . $file);
        event(new DatabaseExported());

        $this->info('Done!');
    }
}
