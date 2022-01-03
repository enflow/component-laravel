<?php

namespace Enflow\Component\Laravel\Console\Commands;

use Illuminate\Console\Command;

class SessionGarbageCollector extends Command
{
    protected $signature = 'session:gc';
    protected $description = 'Clears the sessions garbage if applicable to the current driver';

    public function handle()
    {
        $this->info("Running session garbage collection...");

        session()->getHandler()->gc($this->getSessionLifetimeInSeconds());
    }

    protected function getSessionLifetimeInSeconds(): int
    {
        return config('session.lifetime', null) * 60;
    }
}