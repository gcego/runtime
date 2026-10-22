<?php

declare(strict_types=1);

namespace Gcego\Runtime\Exceptions;

use UnexpectedValueException;

/**
 * Thrown when client-supplied Livewire state does not match the trusted definition.
 *
 * Messages name paths and types, never the rejected values.
 */
class InvalidStateException extends UnexpectedValueException implements RuntimeException
{
    public static function unknownPath(string $property): self
    {
        return new self(sprintf('State update "%s" does not address a state field of this definition.', $property));
    }

    public static function rejected(string $property, string $reason): self
    {
        return new self(sprintf('State update "%s" was rejected: %s', $property, $reason));
    }

    public static function invalidInitialState(string $class, string $reason): self
    {
        return new self(sprintf('%s::initialState() is invalid: %s', $class, $reason));
    }
}
