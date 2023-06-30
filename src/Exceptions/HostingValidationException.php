<?php

namespace Enflow\Component\Laravel\Exceptions;

use Exception;

class HostingValidationException extends Exception
{
    public static function invalidRedisDatabase(int $database, array $reservations): self
    {
        return new static("Redis database '{$database}' is not reserved. Reserved databases: " . collect($reservations)->map(fn($reservation) => $reservation['label'] . ':' . $reservation['database'])->implode(', '));
    }
}