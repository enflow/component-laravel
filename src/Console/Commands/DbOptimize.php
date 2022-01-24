<?php

namespace Enflow\Component\Laravel\Console\Commands;

use Enflow\Component\Laravel\Events\DatabaseImported;
use Enflow\Component\Laravel\Events\DatabaseOptimized;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Symfony\Component\Process\Process;

class DbOptimize extends Command
{
    use CommandHelpers;

    protected $signature = 'db:optimize {--force}';

    protected $description = 'Optimizes the database by running `mysqlcheck`';

    public function handle()
    {
        $connection = config('database.connections.mysql');

        if (! $this->option('force') && ! $this->confirm("Are you sure you want to optimize {$connection['database']}? This might take some time depending on the database size.")) {
            return;
        }

        if (count(Arr::only($connection, ['host', 'username', 'password', 'database'])) < 4) {
            $this->error('All database variables are required.');
            return;
        }

        $this->warn('Starting optimize: ' . $connection['database']);

        $this->timeProcess(
            Process::fromShellCommandline($this->buildCommand($connection))->setTimeout(5)
        );

        event(new DatabaseOptimized());

        $this->info("Optimized {$connection['database']}");
    }

    private function buildCommand(array $connection)
    {
        return "mysqlcheck -o -u{$connection['username']} -p{$connection['password']} -h{$connection['host']} {$connection['database']}";
    }
}
