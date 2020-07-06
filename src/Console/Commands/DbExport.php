<?php

namespace Enflow\Component\Laravel\Console\Commands;

use Enflow\Component\Laravel\Events\DatabaseExported;
use Illuminate\Console\Command;

class DbExport extends Command
{
    protected $signature = 'db:export';

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

        // mysqdump --column-statistics=0 --ssl-mode=REQUIRED --opt --single-transaction --extended-insert --skip-lock-tables --quick --routines --skip-add-locks -uroot -psecret -h127.0.0.1 injectables_booking > db.sql
        try {
            $columnStatistics = version_compare($this->mysqlVersion(), '8.0', '>=') ? '--column-statistics=0' : null;
            $flags = "{$columnStatistics} --ssl-mode=REQUIRED --opt --single-transaction --extended-insert --skip-lock-tables --quick --routines --skip-add-locks";
            $auth = "-u{$username} -p{$password} -h{$hostname} {$database}";
            $ignores = collect(config('database.excluded', []))->map(function (string $table) use ($database) {
                return '--ignore-table=' . $database . '.' . $table;
            })->implode(' ');
            $file = 'db.sql';
            $this->info('Exporting ' . $database);

            $command = "mysqldump {$flags} {$auth} > $file";

//            $process = new Process([$command]); // Exit Code: 127(Command not found)
//            $process->setTimeout(300);
//            $this->timeProcess($process);
            shell_exec($command);

            $this->info('Exported to ' . $file);
            event(new DatabaseExported());
        } finally {
            $this->info('Done!');
        }
    }

    private function mysqlVersion()
    {
        $output = shell_exec('mysql -V');
        preg_match('@[0-9]+\.[0-9]+\.[0-9]+@', $output, $version);
        return $version[0];
    }
}
