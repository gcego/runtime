<?php

declare(strict_types=1);

namespace Gcego\Runtime\Exceptions;

use InvalidArgumentException;

/**
 * config('gcego-runtime') holds an invalid value.
 */
class InvalidConfigurationException extends InvalidArgumentException implements RuntimeException
{
    public static function invalid(string $key, string $reason): self
    {
        return new self(sprintf('Invalid configuration %s: %s', $key, $reason));
    }
}
