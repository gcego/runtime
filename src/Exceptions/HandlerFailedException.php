<?php

declare(strict_types=1);

namespace Gcego\Runtime\Exceptions;

use Throwable;

/**
 * A handler, hook or middleware threw an unexpected exception.
 */
class HandlerFailedException extends ExecutionException
{
    public static function wrap(string $operation, string $handler, Throwable $previous): self
    {
        return new self(sprintf('Operation "%s" failed in %s: %s', $operation, $handler, $previous->getMessage()), ErrorCategory::HandlerFailed, $previous);
    }
}
