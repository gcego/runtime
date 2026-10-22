<?php

declare(strict_types=1);

namespace Gcego\Runtime\Execution\Events;

use Gcego\Runtime\Context\ExecutionContext;
use Gcego\Runtime\Execution\ExecutionResult;
use Gcego\Runtime\Execution\Operation;

/**
 * Dispatched after an operation succeeded.
 */
final readonly class OperationExecuted
{
    public function __construct(
        public Operation $operation,
        public ExecutionContext $context,
        public ExecutionResult $result,
    ) {}
}
