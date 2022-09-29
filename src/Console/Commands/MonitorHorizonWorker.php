<?php

namespace Enflow\Component\Laravel\Console\Commands;

use Enflow\Component\Laravel\Exceptions\HorizonNotRunningException;
use Illuminate\Console\Command;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;

class MonitorHorizonWorker extends Command
{
    protected $signature = 'horizon:monitor';
    protected $description = 'Monitors Horizon to ensure it\'s in a active state.';

    public function handle()
    {
        if (! class_exists(MasterSupervisorRepository::class)) {
            $this->warn("Skipping horizon check, master supervisor repository not found. This most likely means that Horizon is not installed.");

            return 1;
        }

        $masterSupervisorRepository = app(MasterSupervisorRepository::class);

        if (! $masters = $masterSupervisorRepository->all()) {
            $this->error('No master supervisors found. Horizon is not running.');

            report(new HorizonNotRunningException());

            return 1;
        }

        return $this->artisan('horizon:status');
    }
}