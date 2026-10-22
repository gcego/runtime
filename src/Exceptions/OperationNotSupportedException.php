<?php

declare(strict_types=1);

namespace Gcego\Runtime\Exceptions;

/**
 * No handler is registered for the operation (for example, the data adapter is not installed).
 */
class OperationNotSupportedException extends ExecutionException
{
    public static function noHandler(string $type, string $name): self
    {
        return new self(sprintf(
            'No handler is registered for "%s" operations named "%s". Register one with HandlerRegistry::register(\'%s\', YourHandler::class), or install the package that provides it.',
            $type,
            $name,
            $type,
        ), ErrorCategory::Unsupported);
    }

    public static function unknownType(string $type): self
    {
        return new self(sprintf('"%s" is not a registered operation category. Register it with HandlerRegistry::category().', $type), ErrorCategory::Unsupported);
    }
}
