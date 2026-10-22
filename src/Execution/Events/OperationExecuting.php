<?php

declare(strict_types=1);

namespace Gcego\Runtime\Execution\Events;

use Gcego\Runtime\Context\ExecutionContext;
use Gcego\Runtime\Execution\ExecutionTrace;
use Gcego\Runtime\Execution\Operation;

/**
 * Dispatched before an operation enters the pipeline (before authorization).
 */
final readonly class OperationExecuting
{
    public function __construct(
        public Operation $operation,
        public ExecutionContext $context,
        public ExecutionTrace $trace,
    ) {}
}
