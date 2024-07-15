<?php

namespace Enflow\Component\Laravel\Console\Commands;

use Enflow\Component\Laravel\Events\DatabaseSynced;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

class ResetCredentials extends Command
{
    protected $signature = 'reset-credentials {--password=} {--force}';
    protected $description = 'Resets all credentials to a given string for local debugging.';

    public function handle()
    {
        if (app()->environment() !== 'local' && ! $this->option('force')) {
            if (app()->environment('production')) {
                $this->error("For local or development usage only. Cannot be forced.");

                return;
            }

            $this->error("For local usage only. Use --force to override.");

            return;
        }

        if (! in_array(gethostbyname(config('database.connections.mysql.host')), ['127.0.0.1', 'localhost'])) {
            $this->error('Cannot sync: MySQL host is configured to an external connection.');

            return;
        }

        $password = $this->password();

        $this->lookupAuthProviders()->each(function (string $table, string $provider) use ($password) {
            $this->info("Checking for {$table}...");

            if (! Schema::hasColumn($table, 'password')) {
                $this->error("{$table} doesn't contain a 'password' column");

                return;
            }

            if (! $this->option('force') && ! $this->confirm("Resettable! Confirm to reset to '{$password}' on the '{$table}' table' for the '{$provider}' provider?")) {
                return;
            }

            $query = DB::table($table)->whereNotNull('password');

            $count = (clone $query)->count();
            (clone $query)->update(['password' => bcrypt($password)]);

            $this->info($table . ": updated {$count} records.");

            $this->table(['Email', 'Password'], (clone $query)->take(5)->get()->map(function ($item) use ($password) {
                return [$item->email ?? 'unknown email', $password];
            }));
        });
    }

    private function lookupAuthProviders(): Collection
    {
        return collect(config('auth.providers'))
            ->filter(fn($provider) => $provider['driver'] === 'eloquent')
            ->filter(fn($provider) => class_exists($provider['model']))
            ->map(fn($provider) => (new $provider['model'])->getTable());
    }

    private function password(): string
    {
        if ($inputtedPassword = $this->option('password')) {
            return $inputtedPassword;
        }

        if (app()->environment('local')) {
            return 'secret123';
        }

        return Str::random();
    }
}
