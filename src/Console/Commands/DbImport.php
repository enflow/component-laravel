<?php

namespace Enflow\Component\Laravel\Console\Commands;

use Enflow\Component\Laravel\Events\DatabaseImported;
use Illuminate\Console\Command;

class DbImport extends Command
{
    protected $signature = 'db:import';

    protected $description = 'Import database from local file';

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

        if (app()->environment() !== 'local') {
            $this->call('down');
        }

        try {
            $file = 'db.sql';
            if (!file_exists($file)) {
                $this->error("File ({$file}) doesn't exist");
                return;
            }
            $this->info('Importing ' . $database . ' from ' . $file);
            // `mysql -uprofessionel_275_prod -p professionel_275_prod < db.sql`;

            file_put_contents('/tmp/mysql-import-before', 'SET autocommit=0;SET unique_checks=0;SET foreign_key_checks=0;');
            file_put_contents('/tmp/mysql-import-after', 'COMMIT; SET unique_checks=1; SET foreign_key_checks=1;');

            $command = "cat /tmp/mysql-import-before {$file} /tmp/mysql-import-after | mysql -u{$username} -p{$password} -h{$hostname} {$database}";
            shell_exec($command);

            $this->info('Imported');
            event(new DatabaseImported());
        } finally {
            if (app()->environment() !== 'local') {
                $this->call('up');
            }
            $this->info('Done!');
        }
    }

}
