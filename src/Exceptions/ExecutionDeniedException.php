<?php

declare(strict_types=1);

namespace Gcego\Runtime\Exceptions;

use Illuminate\Contracts\Debug\ShouldntReport;
use Throwable;

/**
 * Authorization did not allow the operation, or could not be established (fails closed).
 */
class ExecutionDeniedException extends ExecutionException implements ShouldntReport
{
    public static function forbidden(string $operation, ?Throwable $previous = null): self
    {
        return new self(sprintf('Operation "%s" was denied.', $operation), ErrorCategory::Forbidden, $previous);
    }

    public static function unauthenticated(string $operation, ?Throwable $previous = null): self
    {
        return new self(sprintf('Operation "%s" requires an authenticated user.', $operation), ErrorCategory::Unauthenticated, $previous);
    }

    public static function undecidable(string $operation, Throwable $previous): self
    {
        return new self(sprintf('Operation "%s" was denied because authorization could not be established: %s', $operation, $previous::class), ErrorCategory::Forbidden, $previous);
    }
}
