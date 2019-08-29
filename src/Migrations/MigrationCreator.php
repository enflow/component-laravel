<?php

namespace Enflow\Component\Laravel\Migrations;

use Closure;
use Illuminate\Database\Migrations\MigrationCreator as BaseMigrationCreator;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Illuminate\Filesystem\Filesystem;

class MigrationCreator extends BaseMigrationCreator
{
    public function stubPath()
    {
        return __DIR__.'/stubs';
    }
}
