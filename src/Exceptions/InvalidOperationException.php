<?php

declare(strict_types=1);

namespace Gcego\Runtime\Exceptions;

use Illuminate\Contracts\Debug\ShouldntReport;

/**
 * An operation is malformed: bad type, name, target identifier or input shape.
 */
class InvalidOperationException extends ExecutionException implements ShouldntReport
{
    public static function invalid(string $field, string $reason): self
    {
        return new self(sprintf('Invalid operation %s: %s', $field, $reason), ErrorCategory::InvalidOperation);
    }

    public static function targetRequired(string $type): self
    {
        return new self(sprintf('Operations of type "%s" need a target definition. Use ->on("kind:name").', $type), ErrorCategory::InvalidOperation);
    }

    public static function unexpectedTarget(string $identity, string $expected, string $actual): self
    {
        return new self(sprintf('Target "%s" is a %s; this operation needs a %s.', $identity, $actual, $expected), ErrorCategory::InvalidOperation);
    }
}
