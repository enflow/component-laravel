<?php

namespace Enflow\Component\Laravel\Console\Commands;

use Enflow\Component\Laravel\Events\DatabaseSynced;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

class ResetCredentials extends Command
{
    protected $signature = 'reset-credentials {--password=}';
    protected $description = 'Resets all credentials to a given string for local debugging.';

    public function handle()
    {
        if (app()->environment() !== 'local') {
            $this->error("For local usage only.");

            return;
        }

        if (!in_array(config('database.connections.mysql.host'), ['127.0.0.1', 'localhost'])) {
            $this->error('Cannot sync: MySQL host is configured to an external connection.');

            return;
        }

        $password = $this->option('password') ?? 'secret123';
        $table = $this->lookupAuthTable();

        if (!Schema::hasColumn($table, 'password')) {
            $this->error("{$table} doesn't contain a 'password' column");

            return;
        }

        if (!$this->confirm("Do you want to reset all credentials to '{$password}' on the '{$table}' table'?")) {
            return;
        }

        $query = DB::table($table)->whereNotNull('password');

        $count = (clone $query)->count();
        (clone $query)->update(['password' => bcrypt($password),]);

        $this->info("Updated {$count} records.");

        $this->table(['Email', 'Password'], (clone $query)->take(5)->get()->map(function ($item) use ($password) {
            return [$item->email, $password];
        }));
    }

    private function lookupAuthTable(): string
    {
        $model = config('auth.providers.users.model');

        return (new $model)->getTable();
    }
}
