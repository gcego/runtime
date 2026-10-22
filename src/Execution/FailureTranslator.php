<?php

declare(strict_types=1);

namespace Gcego\Runtime\Execution;

use Gcego\Registry\Exceptions\DefinitionNotFoundException;
use Gcego\Runtime\Exceptions\ExecutionDeniedException;
use Gcego\Runtime\Exceptions\ExecutionException;
use Gcego\Runtime\Exceptions\HandlerFailedException;
use Gcego\Runtime\Exceptions\OperationValidationException;
use Gcego\Runtime\Exceptions\TargetNotFoundException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Converts anything thrown during an execution into one ExecutionException
 * with a consistent ErrorCategory. The original stays available as the previous exception.
 */
final readonly class FailureTranslator
{
    public function translate(Throwable $exception, Operation $operation, ?string $handler = null): ExecutionException
    {
        $key = $operation->key();

        return match (true) {
            $exception instanceof ExecutionException => $exception,
            $exception instanceof ValidationException => OperationValidationException::withErrors($key, self::errors($exception), $exception),
            $exception instanceof AuthenticationException => ExecutionDeniedException::unauthenticated($key, $exception),
            $exception instanceof AuthorizationException => ExecutionDeniedException::forbidden($key, $exception),
            $exception instanceof DefinitionNotFoundException => TargetNotFoundException::forIdentifier($operation->target ?? $key, $exception),
            default => HandlerFailedException::wrap($key, $handler ?? 'the runtime', $exception),
        };
    }

    /**
     * @return array<string, list<string>>
     */
    private static function errors(ValidationException $exception): array
    {
        $errors = [];

        foreach ($exception->errors() as $field => $messages) {
            $errors[(string) $field] = array_values(array_filter(is_array($messages) ? $messages : [$messages], is_string(...)));
        }

        return $errors;
    }
}
