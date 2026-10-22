<?php

declare(strict_types=1);

namespace Gcego\Runtime\Exceptions;

/**
 * An execution tried to cross a user, tenant or nesting boundary.
 */
class ContextViolationException extends ExecutionException
{
    public static function switchedSubject(string $operation): self
    {
        return new self(sprintf('Operation "%s" was started from inside another operation with a different user or tenant. Nested operations must keep their parent\'s subject.', $operation), ErrorCategory::ContextViolation);
    }

    public static function tooDeep(string $operation, int $maxDepth): self
    {
        return new self(sprintf('Operation "%s" exceeded the maximum nesting depth of %d.', $operation, $maxDepth), ErrorCategory::ContextViolation);
    }
}
