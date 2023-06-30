<?php

namespace Enflow\Component\Laravel;

use Enflow\Component\Laravel\Cluster\ClusterStore;
use Enflow\Component\Laravel\Console\Commands\MonitorHorizonWorker;
use Enflow\Component\Laravel\Console\Commands\SessionGarbageCollector;
use Enflow\Component\Laravel\Exceptions\MailConfigurationMissingException;
use Facade\Ignition\Facades\Flare as FacadeFlare;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Debug\ExceptionHandler as IlluminateExceptionHandler;
use Illuminate\Database\Schema\Builder as SchemaBuilder;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use LogicException;
use Spatie\LaravelIgnition\Facades\Flare as SpatieFlare;

class LaravelServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/laravel.php', 'laravel'
        );

        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');

        if ($this->app->bound('twig') && $twig = $this->app->resolved('twig')) {
            $twig->getExtension('core')->setTimezone('Europe/Amsterdam');
        }

        localize(config('app.locale'));

        SchemaBuilder::defaultStringLength(191);

        view()->addNamespace('component-laravel', __DIR__ . '/../resources');

        if (! $this->app->runningInConsole() && ! $this->app->make(IlluminateExceptionHandler::class) instanceof AbstractExceptionHandler && ! View::exists('errors.500')) {
            throw new LogicException("Unable to setup custom error template. Please extend the '\\Enflow\\Component\\Laravel\\AbstractExceptionHandler' class in your '\\App\\Exceptions\\Handler' file.");
        }

        // Allow browsersync to be used in Twig
        // @TODO: move to Tower.
        if (config('twigbridge')) {
            config()->set('twigbridge.extensions.functions', array_merge(config()->get('twigbridge.extensions.functions', []), [
                'browsersync' => [
                    'is_safe' => ['html']
                ],
            ]));
        }

        // Remove index.php from URL
        $kernel = $this->app->make(\Illuminate\Contracts\Http\Kernel::class);
        $kernel->pushMiddleware(Http\Middleware\RemoveIndexPhp::class);
        $kernel->pushMiddleware(Http\Middleware\SecurityHeaders::class);
        $kernel->pushMiddleware(Http\Middleware\Robots::class);

        $this->commands([
            Console\Commands\DbSync::class,
            Console\Commands\DbImport::class,
            Console\Commands\DbExport::class,
            Console\Commands\DbCreate::class,
            Console\Commands\DbOptimize::class,
            Console\Commands\ResetCredentials::class,
            Console\Commands\SessionGarbageCollector::class,
            Console\Commands\MonitorHorizonWorker::class,
            Console\Commands\ValidateHosting::class,
        ]);

        if (config('flare.key')) {
            if (class_exists(FacadeFlare::class)) {
                FacadeFlare::context('Hostname', gethostname());
            } elseif (class_exists(SpatieFlare::class)) {
                SpatieFlare::context('Hostname', gethostname());
            }
        }

        if (class_exists(\Laravel\Horizon\Horizon::class)) {
            $this->app->booted(function () {
                $this->app->make(Schedule::class)->command(MonitorHorizonWorker::class)->everyFifteenMinutes();
            });
        }

        $this->setupSessionGarbageCollector();
    }

    public function register()
    {
        if (config('flare.key')) {
            // Everything should be configured in the app. We just ensure there that local & testing exceptions aren't sent to Flare

            if ($this->app->environment('local', 'testing')) {
                config([
                    'flare.key' => null,
                    'logging.channels.stack.channels' => ['single'],
                ]);
            }
        }

        $this->mailSettings();
    }

    private function mailSettings()
    {
        if (in_array($this->app->environment(), ['testing']) && ! in_array(config('mail.driver'), ['log', 'array'])) {
            config(['mail.driver' => 'array']);
        }

        if (config('mail.from.name') === null) {
            config(['mail.from.name' => config('app.name')]);
        }

        if (! in_array(config('mail.driver'), ['log', 'array']) && (config('mail.from.address') === null || config('mail.from.address') === 'hello@example.com')) {
            throw new MailConfigurationMissingException('Mail configuration is missing. Please set the "mail.from.address" configuration value.');
        }
    }

    private function setupSessionGarbageCollector(): void
    {
        // Session garbage collection in background
        // Issue is that 2% of requests have a long request time due to garbage collection. This should be run in the background.
        if (config('session.driver') === 'file' && ! app()->environment('local', 'testing')) {
            $this->app->booted(function () {
                $this->app->make(Schedule::class)->command(SessionGarbageCollector::class)->hourly();
            });

            config([
                'session.lottery' => [0, 1],
            ]);
        }
    }
}
