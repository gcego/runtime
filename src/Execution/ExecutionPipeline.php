<?php

declare(strict_types=1);

namespace Gcego\Runtime\Execution;

use Closure;
use Gcego\Runtime\Context\ExecutionContext;
use Gcego\Runtime\Contracts\AuthorizesOperation;
use Gcego\Runtime\Contracts\ExecutionAuthorizer;
use Gcego\Runtime\Contracts\ExecutionMiddleware;
use Gcego\Runtime\Contracts\ObservesLifecycle;
use Gcego\Runtime\Contracts\OperationHandler;
use Gcego\Runtime\Contracts\ValidatesOperation;
use Gcego\Runtime\Exceptions\ExecutionDeniedException;
use Gcego\Runtime\Exceptions\ExecutionException;
use Gcego\Runtime\Exceptions\HandlerRegistrationException;
use Gcego\Runtime\Exceptions\OperationValidationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Throwable;

/**
 * The ordered stages of an execution:
 *
 *  1. resolve    the target descriptor through gcego/registry (nothing is loaded yet)
 *  2. authorize  the ExecutionAuthorizer, for every operation; fails closed
 *  3. handler    the registered handler for the operation
 *  4. authorize  the handler's own check (AuthorizesOperation)
 *  5. validate   the input against the handler's rules (ValidatesOperation)
 *  6. execute    before() hook, middleware, handler, succeeded()/failed() hooks
 *
 * Authorization can't be removed or reordered. Middleware is the
 * configurable part, and it only runs once authorization and validation have passed.
 */
final class ExecutionPipeline
{
    /**
     * @var list<class-string<ExecutionMiddleware>|ExecutionMiddleware>
     */
    private array $middleware = [];

    public function __construct(
        private readonly Container $container,
        private readonly HandlerRegistry $handlers,
        private readonly DefinitionResolver $resolver,
        private readonly FailureTranslator $failures,
        private readonly ValidationFactory $validation,
        private readonly ExceptionHandler $exceptions,
    ) {}

    /**
     * Append middleware around every handler (outermost first).
     *
     * @param  string|ExecutionMiddleware  ...$middleware  ExecutionMiddleware instances or class names (checked).
     *
     * @throws HandlerRegistrationException
     */
    public function middleware(string|ExecutionMiddleware ...$middleware): void
    {
        foreach ($middleware as $entry) {
            if (is_string($entry) && (! class_exists($entry) || ! is_a($entry, ExecutionMiddleware::class, true))) {
                throw HandlerRegistrationException::invalidMiddleware($entry);
            }

            if (! in_array($entry, $this->middleware, true)) {
                $this->middleware[] = $entry;
            }
        }
    }

    /**
     * @return list<class-string<ExecutionMiddleware>>
     */
    public function middlewareClasses(): array
    {
        return array_map(static fn (string|ExecutionMiddleware $entry): string => is_string($entry) ? $entry : $entry::class, $this->middleware);
    }

    /**
     * @throws ExecutionException
     */
    public function process(Operation $operation, ExecutionContext $context, ExecutionTrace $trace): ExecutionResult
    {
        $target = $trace->measure('resolve', fn (): ?ExecutionTarget => $operation->target === null ? null : $this->resolver->target($operation->target));
        $request = new ExecutionRequest($operation, $context, $target, $trace);

        $trace->measure('authorize', fn () => $this->authorize($request));

        $handler = $trace->measure('handler', fn (): OperationHandler => $this->handlers->for($operation));
        $trace->handledBy($handler::class);

        if ($handler instanceof AuthorizesOperation) {
            $trace->measure('authorize.handler', fn () => $this->authorizeWithHandler($handler, $request));
        }

        if ($handler instanceof ValidatesOperation) {
            $request = $trace->measure('validate', fn (): ExecutionRequest => $this->validate($handler, $request));
        }

        return $trace->measure('execute', fn (): ExecutionResult => $this->run($handler, $request));
    }

    private function authorize(ExecutionRequest $request): void
    {
        try {
            $this->container->make(ExecutionAuthorizer::class)->authorize($request);
        } catch (AuthenticationException $exception) {
            throw ExecutionDeniedException::unauthenticated($request->operation->key(), $exception);
        } catch (AuthorizationException $exception) {
            throw $this->denied($request, $exception);
        } catch (Throwable $exception) {
            if ($exception instanceof ExecutionException) {
                throw $exception;
            }

            $this->exceptions->report($exception);

            throw ExecutionDeniedException::undecidable($request->operation->key(), $exception);
        }
    }

    private function authorizeWithHandler(AuthorizesOperation $handler, ExecutionRequest $request): void
    {
        try {
            $allowed = $handler->authorize($request);
        } catch (AuthorizationException $exception) {
            throw $this->denied($request, $exception);
        } catch (Throwable $exception) {
            if ($exception instanceof ExecutionException) {
                throw $exception;
            }

            $this->exceptions->report($exception);

            throw ExecutionDeniedException::undecidable($request->operation->key(), $exception);
        }

        if ($allowed !== true) {
            throw $this->denied($request);
        }
    }

    private function denied(ExecutionRequest $request, ?Throwable $previous = null): ExecutionDeniedException
    {
        return $request->context->isGuest()
            ? ExecutionDeniedException::unauthenticated($request->operation->key(), $previous)
            : ExecutionDeniedException::forbidden($request->operation->key(), $previous);
    }

    private function validate(ValidatesOperation $handler, ExecutionRequest $request): ExecutionRequest
    {
        $validator = $this->validation->make($request->operation->input, $handler->rules($request));

        if ($validator->fails()) {
            $errors = [];

            foreach ($validator->errors()->toArray() as $field => $messages) {
                $errors[(string) $field] = array_values(array_map(strval(...), (array) $messages));
            }

            throw OperationValidationException::withErrors($request->operation->key(), $errors);
        }

        /** @var array<string, mixed> $validated */
        $validated = $validator->validated();

        return $request->withValidated($validated);
    }

    private function run(OperationHandler $handler, ExecutionRequest $request): ExecutionResult
    {
        try {
            if ($handler instanceof ObservesLifecycle) {
                $handler->before($request);
            }

            $result = $this->through(static function (ExecutionRequest $request) use ($handler): ExecutionResult {
                $value = $handler->handle($request);

                return $value instanceof ExecutionResult ? $value : ExecutionResult::success($value);
            })($request);
        } catch (Throwable $exception) {
            $failure = $this->failures->translate($exception, $request->operation, $handler::class);

            if ($handler instanceof ObservesLifecycle) {
                $this->quietly(static fn () => $handler->failed($request, $failure));
            }

            throw $failure;
        }

        if ($handler instanceof ObservesLifecycle) {
            $this->quietly(static fn () => $handler->succeeded($request, $result));
        }

        return $result;
    }

    /**
     * @param  Closure(ExecutionRequest): ExecutionResult  $core
     * @return Closure(ExecutionRequest): ExecutionResult
     */
    private function through(Closure $core): Closure
    {
        $next = $core;

        foreach (array_reverse($this->middleware) as $entry) {
            $next = function (ExecutionRequest $request) use ($entry, $next): ExecutionResult {
                $middleware = is_string($entry) ? $this->container->make($entry) : $entry;

                if (! $middleware instanceof ExecutionMiddleware) {
                    throw HandlerRegistrationException::invalidMiddleware(get_debug_type($middleware));
                }

                return $middleware->handle($request, $next);
            };
        }

        return $next;
    }

    private function quietly(Closure $hook): void
    {
        try {
            $hook();
        } catch (Throwable $exception) {
            $this->exceptions->report($exception);
        }
    }
}
