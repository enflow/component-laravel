<?php

namespace Enflow\Component\Laravel;

use Enflow\Component\Laravel\Exceptions\MailConfigurationMissingException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Contracts\Debug\ExceptionHandler as IlluminateExceptionHandler;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Foundation\Application;
use Enflow\Component\Laravel\Cluster\ClusterStore;
use Illuminate\Support\Str;
use LogicException;
use Symfony\Component\Process\Process;

class LaravelServiceProvider extends ServiceProvider
{
    public function boot()
    {
        if ($this->app->bound('twig') && $twig = $this->app->resolved('twig')) {
            $twig->getExtension('core')->setTimezone('Europe/Amsterdam');
        }

        localize(config('app.locale'));

        Schema::defaultStringLength(191);

        view()->addNamespace('component-laravel', __DIR__ . '/../resources');

        if (!$this->app->runningInConsole() && !$this->app->make(IlluminateExceptionHandler::class) instanceof AbstractExceptionHandler && !View::exists('errors.500')) {
            throw new LogicException("Unable to setup custom error template. Please extend the '\\Enflow\\Component\\Laravel\\AbstractExceptionHandler' class in your '\\App\\Exceptions\\Handler' file.");
        }

        if ($this->app->environment() === 'local') {
            // Allow ngrok
            config(['trustedproxy.proxies' => '*']);
        }

        // Allow bugsnag & browsersync to be used in Twig
        if (config('twigbridge')) {
            config()->set('twigbridge.extensions.functions', array_merge(config()->get('twigbridge.extensions.functions', []), [
                'bugsnag_js' => [
                    'is_safe' => ['html']
                ],
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
        ]);

        $this->setClusterVariables();

        if ($bugsnagApiKey = config('services.bugsnag.api_key')) {
            $this->app->make('bugsnag')->setAppVersion(cache()->rememberForever('appVersion-' . config('app.name'), function () {
                return $output = exec("cd {$this->app->basePath()}; git describe --always --tags");
            }));
        }
    }

    public function register()
    {
        if ($bugsnagApiKey = config('services.bugsnag.api_key')) {
            $this->registerBugsnag($bugsnagApiKey);
        }

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
        $this->app->register(\Jenssegers\Date\DateServiceProvider::class);
        $this->app->register(\Spatie\LaravelBlink\BlinkServiceProvider::class);

        $this->mailSettings();
    }

    private function mailSettings()
    {
        // Ensure we cannot misconfigure locally to send real emails
        if (in_array($this->app->environment(), ['local']) && !in_array(config('mail.driver'), ['log', 'array']) && config('mail.mailtrap', true) && !in_array(config('mail.host'), ['mailtrap.io', 'smtp.mailtrap.io', 'smtp.mailspons.com'])) {
            throw new Exceptions\MailConfigurationMissingException("The mail configuration is missing. Please setup a local SMTP trap like mailtrap.io or mailspons.com or use the log or array driver.");
        }

        if (in_array($this->app->environment(), ['testing']) && !in_array(config('mail.driver'), ['log', 'array'])) {
            config(['mail.driver' => 'array']);
        }

        if (config('mail.from.name') === null) {
            config(['mail.from.name' => config('app.name')]);
        }
    }

    private function registerBugsnag(string $bugsnagApiKey)
    {
        $this->app->register(\Bugsnag\BugsnagLaravel\BugsnagServiceProvider::class);

        config([
            'bugsnag.api_key' => $bugsnagApiKey,
            'bugsnag.notify_release_stages' => ['production', 'develop', 'staging'],
            'bugsnag.auto_capture_sessions' => $this->app->environment(['production', 'develop', 'staging']),
        ]);

        // https://docs.bugsnag.com/platforms/php/laravel/
        if (version_compare(Application::VERSION, '5.6', '>=')) {
            config([
                'logging.channels.stack.channels' => ['single', 'bugsnag'],
                'logging.channels.bugsnag.driver' => 'bugsnag',
            ]);
        } else {
            // Deprecated: 5.5 and lower only
            $this->app->alias('bugsnag.logger', \Illuminate\Contracts\Logging\Log::class);
            $this->app->alias('bugsnag.logger', \Psr\Log\LoggerInterface::class);
        }

        $this->commands([
            \Bugsnag\BugsnagLaravel\Commands\DeployCommand::class,
        ]);
    }

    private function setClusterVariables()
    {
        // @TODO: move caching to Redis based caching and sessions in memcached, so no hacky "cluster stores" for flush management have be created.

        if (app()->environment() == 'local' || !preg_match('/clu[0-9].enflow.nl/', gethostname())) {
            return;
        }

        Cache::extend('cluster', function ($app, $config) {
            $config = config('cache.stores.memcached');

            $prefix = $this->getPrefix($config);

            $memcached = $this->app['memcached.connector']->connect(
                $config['servers'],
                $config['persistent_id'] ?? null,
                $config['options'] ?? [],
                array_filter($config['sasl'] ?? [])
            );

            return $this->repository(new ClusterStore($memcached, $prefix));
        });

        config([
            'session.driver' => 'memcached',
            'session.encrypt' => true,
            'cache.prefix' => Str::slug(config('app.name')) . sha1(config('app.uuid', config('app.key'))),
            'cache.default' => 'cluster',
            'cache.stores.cluster' => [
                'driver' => 'cluster',
            ],
        ]);
    }
}
