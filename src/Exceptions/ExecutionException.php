<?php

declare(strict_types=1);

namespace Gcego\Runtime\Exceptions;

use Gcego\Runtime\Execution\ExecutionTrace;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Request;
use RuntimeException as BaseRuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

/**
 * Base class of every failure of an executed operation.
 *
 * The message is for developers and logs. HTTP responses only ever show the
 * category's public message (and validation errors), so internal details
 * never reach the browser.
 */
class ExecutionException extends BaseRuntimeException implements RuntimeException
{
    private ?ExecutionTrace $trace = null;

    final public function __construct(
        string $message,
        public readonly ErrorCategory $category,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $category->status(), $previous);
    }

    public function category(): ErrorCategory
    {
        return $this->category;
    }

    public function status(): int
    {
        return $this->category->status();
    }

    /**
     * The diagnostic trace of the failed execution, once the kernel attached it.
     */
    public function trace(): ?ExecutionTrace
    {
        return $this->trace;
    }

    /**
     * @internal Called by the execution kernel.
     */
    public function attachTrace(ExecutionTrace $trace): static
    {
        $this->trace ??= $trace;

        return $this;
    }

    /**
     * Render through Laravel's own exceptions, so applications get their usual
     * login redirects, 403 and 422 responses and error pages.
     */
    public function render(Request $request): Response
    {
        $handler = app(ExceptionHandler::class);

        return $handler->render($request, match ($this->category) {
            ErrorCategory::Unauthenticated => new AuthenticationException($this->category->publicMessage()),
            ErrorCategory::Forbidden => new AuthorizationException($this->category->publicMessage()),
            default => new HttpException($this->status(), $this->category->publicMessage(), $this),
        });
    }

    /**
     * @return array{category: string, status: int, message: string}
     */
    public function toArray(): array
    {
        return ['category' => $this->category->value, 'status' => $this->status(), 'message' => $this->category->publicMessage()];
    }

    public static function unavailable(string $reason): static
    {
        return new static(sprintf('The runtime cannot execute operations: %s', $reason), ErrorCategory::Unavailable);
    }
}
