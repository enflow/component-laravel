<?php

namespace Enflow\Component\Laravel;

use Illuminate\Contracts\Container\Container;
use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Support\Facades\View;
use Symfony\Component\Console\Exception\CommandNotFoundException;

abstract class AbstractExceptionHandler extends Handler
{
    public function __construct(Container $container)
    {
        parent::__construct($container);

        // Skip the 'command cannot be found' exceptions
        $this->internalDontReport[] = CommandNotFoundException::class;
    }

    protected function registerErrorViewPaths()
    {
        View::replaceNamespace('errors', [
            resource_path('views/errors'),
            __DIR__ . '/../resources/errors',
            __DIR__ . '/views',
        ]);
    }
}
