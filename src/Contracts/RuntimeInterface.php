<?php

declare(strict_types=1);

namespace Gcego\Runtime\Contracts;

use Gcego\Runtime\Context\ExecutionContext;
use Gcego\Runtime\Context\ExecutionContextFactory;
use Gcego\Runtime\Exceptions\ExecutionException;
use Gcego\Runtime\Execution\ExecutionResult;
use Gcego\Runtime\Execution\Operation;

/**
 * The execution entry point. Gcego\Runtime\Runtime implements it, and the
 * Runtime facade resolves it, so tests can replace the kernel with
 * Runtime::swap() or Runtime::shouldReceive().
 */
interface RuntimeInterface
{
    /**
     * @throws ExecutionException with an ErrorCategory on any failure.
     */
    public function execute(Operation $operation, ?ExecutionContext $context = null): ExecutionResult;

    public function attempt(Operation $operation, ?ExecutionContext $context = null): ExecutionResult;

    public function contexts(): ExecutionContextFactory;
}
