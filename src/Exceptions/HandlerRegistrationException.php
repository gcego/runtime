<?php

declare(strict_types=1);

namespace Gcego\Runtime\Exceptions;

use InvalidArgumentException;

/**
 * A handler, category or middleware registration is invalid or conflicts with an existing one.
 */
class HandlerRegistrationException extends InvalidArgumentException implements RuntimeException
{
    public static function conflict(string $key, string $existing, string $new): self
    {
        return new self(sprintf('%s already handles %s; %s cannot replace it. Pass override: true to replace a handler deliberately.', $existing, $key, $new));
    }

    public static function unknownCategory(string $type): self
    {
        return new self(sprintf('"%s" is not a registered operation category. Register it first with HandlerRegistry::category(\'%s\', YourContract::class).', $type, $type));
    }

    public static function categoryConflict(string $type, string $existing, string $new): self
    {
        return new self(sprintf('Operation category "%s" already requires %s; it cannot be redefined to require %s.', $type, $existing, $new));
    }

    public static function invalidContract(string $contract): self
    {
        return new self(sprintf('%s must be an interface extending Gcego\Runtime\Contracts\OperationHandler.', $contract));
    }

    public static function invalidHandler(string $handler, string $contract): self
    {
        return new self(sprintf('%s must be an existing class implementing %s.', $handler, $contract));
    }

    public static function invalidMiddleware(string $middleware): self
    {
        return new self(sprintf('%s must be an existing class implementing Gcego\Runtime\Contracts\ExecutionMiddleware.', $middleware));
    }

    public static function invalidName(string $field, string $value): self
    {
        return new self(sprintf('Invalid %s "%s": use a lowercase identifier such as "delete-subject".', $field, mb_substr($value, 0, 100)));
    }
}
