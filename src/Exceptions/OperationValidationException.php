<?php

declare(strict_types=1);

namespace Gcego\Runtime\Exceptions;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * The operation's input failed validation. Carries the field errors.
 */
class OperationValidationException extends ExecutionException implements ShouldntReport
{
    /**
     * @var array<string, list<string>>
     */
    private array $errors = [];

    /**
     * @param  array<string, list<string>>  $errors
     */
    public static function withErrors(string $operation, array $errors, ?ValidationException $previous = null): self
    {
        $exception = new self(sprintf('The input of operation "%s" is invalid (%d field(s)).', $operation, count($errors)), ErrorCategory::ValidationFailed, $previous);
        $exception->errors = $errors;

        return $exception;
    }

    /**
     * @return array<string, list<string>>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    public function render(Request $request): Response
    {
        return app(ExceptionHandler::class)->render($request, ValidationException::withMessages($this->errors));
    }

    /**
     * @return array{category: string, status: int, message: string, errors: array<string, list<string>>}
     */
    public function toArray(): array
    {
        return [...parent::toArray(), 'errors' => $this->errors];
    }
}
