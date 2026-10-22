<?php

declare(strict_types=1);

namespace Gcego\Runtime\Contracts;

use Gcego\Runtime\Execution\ExecutionRequest;
use Gcego\Runtime\Execution\ExecutionResult;

/**
 * Executes one category of operations, after the kernel has resolved the
 * target, authorized the request and validated the input.
 *
 * Handlers are built once through the container and must be stateless: all
 * per-execution data arrives in the ExecutionRequest. Return any value (it is
 * wrapped in a successful ExecutionResult) or an ExecutionResult.
 */
interface OperationHandler
{
    public function handle(ExecutionRequest $request): mixed;
}
