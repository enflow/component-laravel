<?php

namespace Enflow\Component\Laravel\Console\Commands;

use Enflow\Component\Laravel\Events\DatabaseSynced;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class DbCreate extends Command
{
    protected $signature = 'db:create';

    public function handle()
    {
        if (!app()->environment('local')) {
            $this->error('Only for local development.');

            return;
        }

        $database = config("database.connections.mysql.database");

        config(["database.connections.mysql.database" => null]);

        $charset = config("database.connections.mysql.charset", 'utf8mb4');
        $collation = config("database.connections.mysql.collation", 'utf8mb4_unicode_ci');

        DB::statement("CREATE DATABASE IF NOT EXISTS $database CHARACTER SET $charset COLLATE $collation;");
    }
}
