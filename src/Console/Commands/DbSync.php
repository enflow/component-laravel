<?php

namespace Enflow\Component\Laravel\Console\Commands;

use Enflow\Component\Laravel\Events\DatabaseSynced;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

class DbSync extends Command
{
    protected $signature = 'db:sync';

    protected $description = 'Sync the database with a remote DB server';

    public function handle()
    {
        // CREATE USER 'local_syncer'@'office.enflow.nl' IDENTIFIED BY 'XXXXXXXXXXXX';
        // GRANT SELECT, RELOAD, REPLICATION CLIENT, SHOW VIEW, EVENT, TRIGGER ON *.* TO 'local_syncer'@'office.enflow.nl';
        // FLUSH PRIVILEGES;
        // Add to Forge network tab as well

        $hostname = config('syncer.hostname') ?? $this->ask('Syncer hostname?', 'web0.clu0.enflow.nl');
        $username = config('syncer.username') ?? $this->ask('Syncer username?');
        $password = config('syncer.password') ?? $this->ask('Syncer password?');
        $database = config('syncer.databases') ? $this->choice('Which database do you want to import?', config('syncer.databases')) : [$username];

        if (app()->environment() == 'production') {
            $this->error('Cannot run in production');
            return;
        }

        if (!in_array(gethostbyname(config('database.connections.mysql.host')), ['127.0.0.1', 'localhost'])){
            $this->error('Can only sync to local');
            return;
        }

        if (app()->environment() !== 'local') {
            $this->call('down');
        }

        try {
            $this->info('Exporting ' . $database);

            $columnStatistics = version_compare($this->mysqlVersion(), '8.0', '>=') ? '--column-statistics=0' : null;
            $flags = "{$columnStatistics} --ssl-mode=REQUIRED --opt --single-transaction --extended-insert --skip-lock-tables --quick --routines -u{$username} -p{$password} -h{$hostname}";

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

                $process = new Process($command . " {$tmpFile}");
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
        $host = config('database.connections.mysql.host');
        $username = config('database.connections.mysql.username');
        $password = config('database.connections.mysql.password');
        $database = config('database.connections.mysql.database');

        file_put_contents('/tmp/mysql-import-before', 'SET autocommit=0;SET unique_checks=0;SET foreign_key_checks=0;');
        file_put_contents('/tmp/mysql-import-after', 'COMMIT; SET unique_checks=1; SET foreign_key_checks=1;');

        foreach ($files as $file) {
            $process = new Process("cat /tmp/mysql-import-before {$file} /tmp/mysql-import-after | mysql -u{$username} -p{$password} -h{$host} {$database}");
            $process->setTimeout(600);
            $this->timeProcess($process);
        }
    }

    private function resetPasswords()
    {
        $password = app()->environment() === 'local' ? 'secret123' : config('syncer.develop_password', 'secret123');

        if (Schema::hasColumn('accounts', 'password')) {
            DB::table('accounts')->update(['password' => bcrypt($password),]);
        }

        if (Schema::hasColumn('users', 'password')) {
            DB::table('users')->update(['password' => bcrypt($password),]);
        }
    }

    private function timeProcess(Process $process)
    {
        $process->start();

        ProgressBar::setPlaceholderFormatterDefinition('elapsed_seconds', function (ProgressBar $bar, OutputInterface $output) {
            return (time() - $bar->getStartTime()) . ' secs';
        });

        $progressBar = new ProgressBar($this->output, $process->getTimeout());
        $progressBar->setFormat(' %elapsed_seconds%/%estimated%');

        while ($process->isRunning()) {
            sleep(1);

            $progressBar->advance();
        }

        $progressBar->finish();

        $this->info('');
    }

    private function mysqlVersion()
    {
        $output = shell_exec('mysql -V');
        preg_match('@[0-9]+\.[0-9]+\.[0-9]+@', $output, $version);
        return $version[0];
    }
}
