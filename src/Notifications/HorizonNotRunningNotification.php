<?php

namespace Enflow\Component\Laravel\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\SlackMessage;

class HorizonNotRunningNotification extends Notification
{
    public function via($notifiable): array
    {
        return ['slack'];
    }

    public function toSlack($notifiable): SlackMessage
    {
        return (new SlackMessage)
            ->error()
            ->content('Horizon is not running.')
            // Add hostname & link to the Horizon dashboard
            ->attachment(function ($attachment) {
                $attachment
                    ->fields([
                        'URL' => url('/'),
                        'Environment' => app()->environment(),
                    ])
                    ->action('View', url('/horizon'));
            });
    }

    public static function shouldSend(): bool
    {
        if (! app()->environment('production')) {
            return false;
        }

        if (! config('laravel.horizon_monitor_slack_notification_url')) {
            logger()->error('HorizonNotRunningNotification: No slack notification URL configured.');

            return false;
        }

        $downSinceKey = 'horizon-down-since';
        $notifiedOnceKey = 'horizon-notified-once';

        $downSince = cache()->get($downSinceKey);

        // If "first time down", record timestamp, skip notification
        if (! $downSince) {
            cache()->forever($downSinceKey, now());

            return false;
        }

        // If we've already sent one notification, don't send again
        if (cache()->has($notifiedOnceKey)) {
            return false;
        }

        // It's down, not yet notified
        // Send notification once we cross the 7-min threshold
        if ($downSince->diffInMinutes(now()) >= 7) {
            // Mark that we have sent the notification, so we don't send it again for 30 minutes.
            cache()->remember($notifiedOnceKey, now()->addMinutes(30), true);

            return true;
        }

        return false;
    }
}