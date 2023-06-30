<?php

namespace Enflow\Component\Laravel\Console\Commands;

use Enflow\Component\Laravel\Exceptions\HostingValidationException;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;

class ValidateHosting extends Command
{
    protected $signature = 'hosting:validate';
    protected $description = 'Validate the deployed instance is conform the hosting configuration.';

    public function handle(): int
    {
        // Ensure hosting.json exists.
        if (! file_exists($hostingJsonPath = base_path('../../hosting.json'))) {
            $this->error('hosting.json not found. Please ensure it\'s written via the deployer.');

            return 1;
        }

        $hosting = json_decode(file_get_contents($hostingJsonPath), true);

        $this->validateRedisDatabase($hosting);

        $this->info('Hosting configuration is valid.');

        return 0;
    }

    private function validateRedisDatabase(array $hosting): void
    {
        $redisReservations = $hosting['redis_reservations'] ?? [];

        $connections = Arr::except(config('database.redis'), ['client', 'options']);

        foreach ($connections as $label => $connection) {
            $database = $connection['database'] ?? null;

            if (($connection['host'] ?? null) !== 'redis.ha0.enflow.network') {
                $this->warn("Redis connection '{$label}' is not using the Redis instance.");

                continue;
            }

            $isUsingDatabase = match ($label) {
                'cache' => config('cache.default') === 'redis' && config('cache.stores.redis.connection') === 'cache',
                default => true,
            };

            if (! $isUsingDatabase) {
                $this->warn("Redis connection '{$label}' is not using a database.");

                continue;
            }

            if ($database === null || ! in_array($database, Arr::pluck($redisReservations, 'database'))) {
                throw HostingValidationException::invalidRedisDatabase($database, $redisReservations);
            }
        }
    }
}