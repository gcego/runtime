<?php

declare(strict_types=1);

namespace Gcego\Runtime\Exceptions;

use Illuminate\Contracts\Debug\ShouldntReport;
use Throwable;

/**
 * The operation's target is not a registered definition.
 */
class TargetNotFoundException extends ExecutionException implements ShouldntReport
{
    public static function forIdentifier(string $identifier, ?Throwable $previous = null): self
    {
        return new self(sprintf('No definition is registered as "%s".', mb_substr($identifier, 0, 100)), ErrorCategory::NotFound, $previous);
    }
}
