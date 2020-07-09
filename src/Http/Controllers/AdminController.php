<?php

namespace Enflow\Component\Laravel\Http\Controllers;

use Illuminate\Support\Str;

class AdminController
{
    public function endpoint(string $endpoint)
    {
        abort_unless(auth()->check() && (auth()->user()->email ?? null) && Str::endsWith(auth()->user()->email, '@enflow.nl'), 403);

        switch ($endpoint) {
            case 'flare':
                $url = config('laravel.endpoints.flare.base_url') . config('laravel.endpoints.flare.project');
                break;
            case 'mailspons':
                $url = config('laravel.endpoints.mailspons.base_url') . config('laravel.endpoints.mailspons.inbox');
                break;
            case 's3':
                $url = config('laravel.endpoints.s3.base_url') . config('filesystems.disks.s3.bucket');
                break;
            case 'git':
                $url = config('laravel.endpoints.git.base_url') . config('laravel.endpoints.git.project');
                break;
            case 'chipper':
                $url = config('laravel.endpoints.chipper.base_url') . config('laravel.endpoints.chipper.project');
                break;
            case 'phpmyadmin':
                $url = $this->databaseRoute();
                break;
            default:
                abort(403);
        }

        return redirect($url ?? '/');
    }

    private function databaseRoute()
    {
        if (auth()->check() && Str::contains(auth()->user()->email, '@enflow.nl')) {
            $baseUrl = config('laravel.endpoints.phpmyadmin.base_url');
            $database = config('laravel.endpoints.phpmyadmin.database') ?? config('database.connections.mysql.database');
            $password = config('laravel.endpoints.phpmyadmin.password') ?? config('database.connections.mysql.password');
            $username = config('laravel.endpoints.phpmyadmin.username') ?? config('database.connections.mysql.username');
            $table = config('laravel.endpoints.phpmyadmin.table', 'users');

            return "{$baseUrl}?db={$database}&pma_password={$password}&pma_username={$username}&target=tbl_sql.php&table={$table}";
        }
    }
}
