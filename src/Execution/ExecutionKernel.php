<?php

declare(strict_types=1);

namespace Gcego\Runtime\Execution;

use Gcego\Core\Contracts\FrameworkInterface;
use Gcego\Runtime\Context\ExecutionContext;
use Gcego\Runtime\Exceptions\ExecutionException;
use Gcego\Runtime\Execution\Events\OperationExecuted;
use Gcego\Runtime\Execution\Events\OperationExecuting;
use Gcego\Runtime\Execution\Events\OperationFailed;
use Gcego\Runtime\RuntimeConfig;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Events\Dispatcher;
use Throwable;

/**
 * Executes operations: checks that GCEGO has booted, isolates the context,
 * runs the pipeline, translates failures, records a trace and dispatches
 * lifecycle events.
 *
 * A stateless singleton. The per-request ExecutionScope is resolved from the
 * container on every call, never captured.
 */
final readonly class ExecutionKernel
{
    public function __construct(
        private Container $container,
        private ExecutionPipeline $pipeline,
        private FailureTranslator $failures,
        private RuntimeConfig $config,
        private Dispatcher $events,
        private ExceptionHandler $exceptions,
    ) {}

    /**
     * @throws ExecutionException
     */
    public function execute(Operation $operation, ExecutionContext $context): ExecutionResult
    {
        $this->assertAvailable();

        $scope = $this->container->make(ExecutionScope::class);
        $trace = new ExecutionTrace($operation, $context->request->id, $scope->depth());

        try {
            $scope->enter($context, $operation, $this->config->maxNesting);
        } catch (ExecutionException $exception) {
            $trace->finish(ResultStatus::Failed, $exception->category);

            throw $exception->attachTrace($trace);
        }

        try {
            $this->events->dispatch(new OperationExecuting($operation, $context, $trace));
            $result = $this->pipeline->process($operation, $context, $trace)->withTrace($trace);
            $trace->finish(ResultStatus::Succeeded);
        } catch (Throwable $exception) {
            $failure = $this->failures->translate($exception, $operation, $trace->handler())->attachTrace($trace);
            $trace->finish(ResultStatus::Failed, $failure->category);
            $this->events->dispatch(new OperationFailed($operation, $context, $failure));

            throw $failure;
        } finally {
            $scope->leave();
        }

        $this->events->dispatch(new OperationExecuted($operation, $context, $result));

        return $result;
    }

    /**
     * Like execute(), but failures come back as failed results. Server-side
     * failures are reported to Laravel's exception handler.
     */
    public function attempt(Operation $operation, ExecutionContext $context): ExecutionResult
    {
        try {
            return $this->execute($operation, $context);
        } catch (ExecutionException $exception) {
            if (! $exception->category->isClientError()) {
                $this->exceptions->report($exception);
            }

            return ExecutionResult::failure($exception);
        }
    }

    /**
     * The context of the operation being executed, when called from inside a handler.
     */
    public function currentContext(): ?ExecutionContext
    {
        return $this->container->make(ExecutionScope::class)->current();
    }

    private function assertAvailable(): void
    {
        if (! $this->container->bound(FrameworkInterface::class)) {
            throw ExecutionException::unavailable('gcego/core is not registered.');
        }

        $framework = $this->container->make(FrameworkInterface::class);

        if (! $framework instanceof FrameworkInterface || ! $framework->isBooted()) {
            throw ExecutionException::unavailable('GCEGO Studio has not booted (or is disabled). See "php artisan gcego:diagnose".');
        }
    }
}
