<?php

declare(strict_types=1);

namespace Gcego\Runtime\Contracts;

use Gcego\Runtime\Exceptions\ExecutionException;
use Gcego\Runtime\Execution\ExecutionRequest;
use Gcego\Runtime\Execution\ExecutionResult;

/**
 * Operation-level lifecycle hooks for a handler.
 *
 * before() runs after authorization and validation; throwing there fails
 * the operation. Exceptions in succeeded() and failed() are reported and
 * never change the outcome.
 */
interface ObservesLifecycle
{
    public function before(ExecutionRequest $request): void;

    public function succeeded(ExecutionRequest $request, ExecutionResult $result): void;

    public function failed(ExecutionRequest $request, ExecutionException $exception): void;
}
