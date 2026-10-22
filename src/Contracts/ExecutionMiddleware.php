<?php

declare(strict_types=1);

namespace Gcego\Runtime\Contracts;

use Closure;
use Gcego\Runtime\Execution\ExecutionRequest;
use Gcego\Runtime\Execution\ExecutionResult;

/**
 * Wraps handlers, for cross-cutting concerns such as transactions, rate
 * limits, auditing or caching.
 *
 * Middleware runs after authorization and validation, so it never sees an
 * operation the context may not execute.
 */
interface ExecutionMiddleware
{
    /**
     * @param  Closure(ExecutionRequest): ExecutionResult  $next
     */
    public function handle(ExecutionRequest $request, Closure $next): ExecutionResult;
}
