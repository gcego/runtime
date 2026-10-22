<?php

declare(strict_types=1);

namespace Gcego\Runtime\Exceptions;

/**
 * The consistent failure categories of an executed operation.
 *
 * Every ExecutionException carries one, so callers, HTTP responses and
 * diagnostics can react to the kind of failure without parsing messages.
 */
enum ErrorCategory: string
{
    case InvalidOperation = 'invalid_operation';
    case Unauthenticated = 'unauthenticated';
    case Forbidden = 'forbidden';
    case NotFound = 'not_found';
    case ValidationFailed = 'validation_failed';
    case Unsupported = 'unsupported';
    case ContextViolation = 'context_violation';
    case HandlerFailed = 'handler_failed';
    case Unavailable = 'unavailable';

    public function status(): int
    {
        return match ($this) {
            self::InvalidOperation => 400,
            self::Unauthenticated => 401,
            self::Forbidden => 403,
            self::NotFound => 404,
            self::ValidationFailed => 422,
            self::Unsupported => 501,
            self::ContextViolation, self::HandlerFailed => 500,
            self::Unavailable => 503,
        };
    }

    /**
     * Whether the caller caused the failure (4xx) rather than the server (5xx).
     */
    public function isClientError(): bool
    {
        return $this->status() < 500;
    }

    /**
     * A message that is safe to show to any user.
     */
    public function publicMessage(): string
    {
        return match ($this) {
            self::InvalidOperation => 'The operation is invalid.',
            self::Unauthenticated => 'Authentication is required.',
            self::Forbidden => 'This action is unauthorized.',
            self::NotFound => 'The requested definition was not found.',
            self::ValidationFailed => 'The given data was invalid.',
            self::Unsupported => 'This operation is not supported.',
            self::ContextViolation, self::HandlerFailed => 'The operation could not be completed.',
            self::Unavailable => 'The service is unavailable.',
        };
    }
}
