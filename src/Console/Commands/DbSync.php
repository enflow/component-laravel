<?php

namespace Enflow\Component\Laravel\Console\Commands;

use Enflow\Component\Laravel\Events\DatabaseSynced;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class DbSync extends Command
{
    use CommandHelpers;

    protected $signature = 'db:sync {--hostname=} {--port=} {--username=} {--password=} {--database=}';

    protected $description = 'Sync the database with a remote DB server';

    public function handle()
    {
        $hostname = $this->option('hostname') ?: (config('syncer.hostname') ?? $this->ask('Syncer hostname?', 'db.ha0.enflow.network'));
        $port = $this->option('port') ?: (config('syncer.port') ?? 3306);
        $username = $this->option('username') ?: (config('syncer.username') ?? $this->ask('Syncer username?'));
        $password = $this->option('password') ?: (config('syncer.password') ?? $this->secret('Syncer password?'));
        $database = $this->option('database') ?: (config('syncer.databases') ? $this->choice('Which database do you want to import?', config('syncer.databases')) : $username);

        if (empty($hostname) || empty($username) || empty($password) || empty($database)) {
            $this->error('All syncer variables are required.');
            return;
        }

        if (app()->environment() == 'production') {
            $this->error('Cannot run in production');
            return;
        }

        if (! in_array(gethostbyname(config('database.connections.mysql.host')), config('syncer.valid_hosts', ['127.0.0.1', 'localhost']))) {
            $this->error('Can only sync to local');
            return;
        }

        if (app()->environment() !== 'local') {
            $this->call('down');
        }

        try {
            $this->info('Exporting ' . $database);

            $columnStatistics = ($mysqlVersion = $this->mysqlVersion()) && version_compare($mysqlVersion, '8.0', '>=') ? '--column-statistics=0' : null;
            $flags = "{$columnStatistics} --opt --single-transaction --extended-insert --skip-add-locks --skip-lock-tables --no-tablespaces --quick -u{$username} -p{$password} -h{$hostname} --port={$port}";

            $ignores = collect(config('syncer.excluded', []))->map(fn(string $table) => '--ignore-table=' . $database . '.' . $table)->implode(' ');

            $commands = [
                "structure" => "mysqldump {$flags} --no-data {$database} >",
                "data" => "mysqldump {$flags} --no-create-info {$ignores} {$database} >>",
            ];

            $tmpFiles = [];
            foreach ($commands as $name => $command) {
                $this->info("Exporting {$name}");

                $tmpFile = tempnam(sys_get_temp_dir(), "db_sync_");

                $this->timeProcess(
                    Process::fromShellCommandline($command . " {$tmpFile}")->setTimeout(300)
                );

                $tmpFiles[] = $tmpFile;
            }

            $this->info('Dropping tables');
            $this->dropAllTables();

            $this->info('Importing SQL');
            $this->importAndDeleteSqlFiles($tmpFiles);

            $this->info('Optimizing database');
            $this->call('db:optimize', ['--force' => true]);

            $this->info('Resetting passwords');
            $this->resetPasswords();

            $this->info("Running migrations");
            $this->call('migrate', ['--force' => true]);

            $this->info("Clearing cache");
            $this->call('cache:clear');

            event(new DatabaseSynced());
        } finally {
            if (app()->environment() !== 'local') {
                $this->call('up');
            }
        }
    }

    private function dropAllTables()
    {
        Schema::disableForeignKeyConstraints();

        foreach (DB::select('SHOW TABLES') as $table) {
            Schema::drop(get_object_vars($table)[key($table)]);
        }

        Schema::enableForeignKeyConstraints();
    }

    private function importAndDeleteSqlFiles($files)
    {
        foreach ($files as $file) {
            $this->call('db:import', [
                'file' => $file,
                '--force' => true,
                '--skip-maintenance-mode' => false,
            ]);

            unlink($file);
        }
    }

    private function resetPasswords()
    {
        $password = app()->environment() === 'local' ?
            'secret123' : config('syncer.develop_password', Str::random(16));

        $this->info(" - Resetted all accounts to {$password}");

        if (Schema::hasColumn('accounts', 'password')) {
            DB::table('accounts')->update(['password' => bcrypt($password),]);
        }

        if (Schema::hasColumn('users', 'password')) {
            DB::table('users')->update(['password' => bcrypt($password),]);
        }
    }
}
