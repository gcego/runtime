<?php

declare(strict_types=1);

namespace Gcego\Runtime;

use Gcego\Runtime\Context\ExecutionContext;
use Gcego\Runtime\Context\ExecutionContextFactory;
use Gcego\Runtime\Contracts\RuntimeInterface;
use Gcego\Runtime\Exceptions\ExecutionException;
use Gcego\Runtime\Execution\ExecutionKernel;
use Gcego\Runtime\Execution\ExecutionResult;
use Gcego\Runtime\Execution\Operation;

/**
 * The execution entry point (a stateless container singleton).
 *
 *     $runtime->execute(Operation::action('archive')->on('blueprint:subjects')->with(['record' => 5]));
 *     $runtime->attempt($operation, $runtime->contexts()->system());   // failures as results
 *
 * Lifecycle: context -> resolve -> authorize -> handler -> validate -> middleware -> handler -> result.
 */
final readonly class Runtime implements RuntimeInterface
{
    public function __construct(
        private ExecutionKernel $kernel,
        private ExecutionContextFactory $contexts,
    ) {}

    /**
     * Execute an operation and return its successful result.
     *
     * Without a context, the operation runs as the current request's user
     * (or, from inside a handler, with the parent operation's context).
     *
     * @throws ExecutionException with an ErrorCategory on any failure.
     */
    public function execute(Operation $operation, ?ExecutionContext $context = null): ExecutionResult
    {
        return $this->kernel->execute($operation, $context ?? $this->defaultContext());
    }

    /**
     * Execute an operation; failures come back as failed results instead of exceptions.
     */
    public function attempt(Operation $operation, ?ExecutionContext $context = null): ExecutionResult
    {
        return $this->kernel->attempt($operation, $context ?? $this->defaultContext());
    }

    /**
     * The factory for trusted execution contexts.
     */
    public function contexts(): ExecutionContextFactory
    {
        return $this->contexts;
    }

    private function defaultContext(): ExecutionContext
    {
        return $this->kernel->currentContext() ?? $this->contexts->current();
    }
}
