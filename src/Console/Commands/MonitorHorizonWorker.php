<?php

namespace Enflow\Component\Laravel\Console\Commands;

use Composer\InstalledVersions;
use Enflow\Component\Laravel\Exceptions\HorizonNotRunningException;
use Enflow\Component\Laravel\Notifications\HorizonNotRunningNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;

class MonitorHorizonWorker extends Command
{
    protected $signature = 'horizon:monitor';
    protected $description = 'Monitors Horizon to ensure it\'s in a active state.';

    public function handle(): int
    {
        if (! InstalledVersions::isInstalled('laravel/horizon')) {
            $this->warn("Skipping horizon check; Horizon is not used in this project.");

            return 1;
        }

        $masterSupervisorRepository = app(MasterSupervisorRepository::class);

        if (! $masterSupervisorRepository->all()) {
            $this->error('No master supervisors found. Horizon is not running.');

            report(new HorizonNotRunningException());

            // To not overload notifications, only send a notification in production
            if (HorizonNotRunningNotification::shouldSend()) {
                Notification::route('slack', config('laravel.horizon_monitor_slack_notification_url'))
                    ->notify(new HorizonNotRunningNotification());

                $this->info('Notification sent.');
            }

            return 1;
        }

        return $this->call('horizon:status');
    }
}