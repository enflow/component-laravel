<?php

namespace Enflow\Component\Laravel;

use Enflow\Component\Laravel\Exceptions\MailConfigurationMissingException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Contracts\Debug\ExceptionHandler as IlluminateExceptionHandler;
use Illuminate\Database\Schema\Builder as SchemaBuilder;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Foundation\Application;
use Enflow\Component\Laravel\Cluster\ClusterStore;
use Illuminate\Support\Str;
use LogicException;
use Symfony\Component\Process\Process;
use Facade\Ignition\Facades\Flare;

class LaravelServiceProvider extends ServiceProvider
{
    public function boot()
    {
        if ($this->app->bound('twig') && $twig = $this->app->resolved('twig')) {
            $twig->getExtension('core')->setTimezone('Europe/Amsterdam');
        }

        localize(config('app.locale'));

        SchemaBuilder::defaultStringLength(191);

        view()->addNamespace('component-laravel', __DIR__ . '/../resources');

        if (!$this->app->runningInConsole() && !$this->app->make(IlluminateExceptionHandler::class) instanceof AbstractExceptionHandler && !View::exists('errors.500')) {
            throw new LogicException("Unable to setup custom error template. Please extend the '\\Enflow\\Component\\Laravel\\AbstractExceptionHandler' class in your '\\App\\Exceptions\\Handler' file.");
        }

        if (config('queue.default') === 'beanstalkd' && config('queue.connections.beanstalkd.queue') === 'default') {
            throw new LogicException("Unable to setup queue, beanstalkd is listing to 'default' queue which conflicts with other application. Please ensure that the correct queue is set. Prefered format: 'applicationname_env'. Example: 'mijnenflow_production'");
        }

        if ($this->app->environment() === 'local') {
            // Allow ngrok
            config(['trustedproxy.proxies' => '*']);
        }

        // Allow browsersync to be used in Twig
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
            Console\Commands\ResetCredentials::class,
        ]);

        if ($flareApiKey = config('flare.key')) {
            Flare::context('Hostname', gethostname());
        }
    }

    public function register()
    {
        if ($flareApiKey = config('flare.key')) {
            // Everything should be configured in the app. We just ensure there that local & testing exceptions aren't sent to Flare

            if ($this->app->environment('local', 'testing')) {
                config([
                    'flare.key' => null,
                    'logging.channels.stack.channels' => ['single'],
                ]);
            }
        }

        // Ensure the host is set correctly when using ngrok (https://trello.com/c/36euiq0A/285-ngrok-originalhost-handling)
        if ($this->app->environment() === 'local' && isset($_SERVER['HTTP_X_ORIGINAL_HOST'])) {
            request()->headers->set('host', $_SERVER['HTTP_X_ORIGINAL_HOST']);
        }

        $this->app->register(\Clockwork\Support\Laravel\ClockworkServiceProvider::class);
        $this->app->register(\Spatie\LaravelBlink\BlinkServiceProvider::class);

        $this->mailSettings();
    }

    private function mailSettings()
    {
        if (in_array($this->app->environment(), ['testing']) && !in_array(config('mail.driver'), ['log', 'array'])) {
            config(['mail.driver' => 'array']);
        }

        if (config('mail.from.name') === null) {
            config(['mail.from.name' => config('app.name')]);
        }
    }
}
