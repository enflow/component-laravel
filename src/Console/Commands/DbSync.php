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

    protected $signature = 'db:sync {--database=}';

    protected $description = 'Sync the database with a remote DB server';

    public function handle()
    {
        // CREATE USER 'syncer'@'%' IDENTIFIED BY 'XXXXXXXXXXXX';
        // GRANT SELECT, RELOAD, REPLICATION CLIENT, SHOW VIEW, EVENT, TRIGGER ON *.* TO 'syncer'@'%';
        // FLUSH PRIVILEGES;
        // Add to Forge network tab as well

        $hostname = config('syncer.hostname') ?? $this->ask('Syncer hostname?', 'db.clu0.enflow.nl');
        $port = config('syncer.port') ?? 3306;
        $username = config('syncer.username') ?? $this->ask('Syncer username?');
        $password = config('syncer.password') ?? $this->secret('Syncer password?');
        $database = $this->option('database') ?: (config('syncer.databases') ? $this->choice('Which database do you want to import?', config('syncer.databases')) : $username);

        if (empty($hostname) || empty($username) || empty($password) || empty($database)) {
            $this->error('All syncer variables are required.');
            return;
        }

        if (app()->environment() == 'production') {
            $this->error('Cannot run in production');
            return;
        }

        if (!in_array(gethostbyname(config('database.connections.mysql.host')), ['127.0.0.1', 'localhost'])) {
            $this->error('Can only sync to local');
            return;
        }

        if (app()->environment() !== 'local') {
            $this->call('down');
        }

        try {
            $this->info('Exporting ' . $database);

            $columnStatistics = version_compare($this->mysqlVersion(), '8.0', '>=') ? '--column-statistics=0' : null;
            $flags = "{$columnStatistics} --ssl-mode=REQUIRED --opt --single-transaction --extended-insert --skip-add-locks --skip-lock-tables --no-tablespaces --quick --routines -u{$username} -p{$password} -h{$hostname} --port={$port}";

            $ignores = collect(config('syncer.excluded', []))->map(function (string $table) use ($database) {
                return '--ignore-table=' . $database . '.' . $table;
            })->implode(' ');

            $tmpFiles = [];
            foreach ([
                         "structure" => "mysqldump {$flags} --no-data {$database} >",
                         "data" => "mysqldump {$flags} --no-create-info {$ignores} {$database} >>",
                     ] as $name => $command) {
                $this->info("Exporting {$name}");

                $tmpFile = tempnam(sys_get_temp_dir(), "db_sync_");

                $process = Process::fromShellCommandline($command . " {$tmpFile}");
                $process->setTimeout(300);
                $this->timeProcess($process);

                $tmpFiles[] = $tmpFile;
            }

            $this->info('Dropping tables');
            $this->dropAllTables();

            $this->info('Importing SQL');
            $this->importingSqlFiles($tmpFiles);
            unlink($tmpFile);

            $this->info('Resetting passwords');
            $this->resetPasswords();

            $this->info("Running migrations");
            $this->call('migrate', ['--force' => '']);

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

    private function importingSqlFiles($files)
    {
        foreach ($files as $file) {
            Artisan::call('db:import', ['file' => $file, '--force' => true]);
        }
    }

    private function resetPasswords()
    {
        $password = app()->environment() === 'local' ?
            'secret123' : config('syncer.develop_password', Str::random(8));

        $this->info(" - Resetted all accounts to {$password}");

        if (Schema::hasColumn('accounts', 'password')) {
            DB::table('accounts')->update(['password' => bcrypt($password),]);
        }

        if (Schema::hasColumn('users', 'password')) {
            DB::table('users')->update(['password' => bcrypt($password),]);
        }
    }
}
