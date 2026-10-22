<?php

declare(strict_types=1);

namespace Gcego\Runtime\Execution\Events;

use Gcego\Runtime\Context\ExecutionContext;
use Gcego\Runtime\Exceptions\ExecutionException;
use Gcego\Runtime\Execution\Operation;

/**
 * Dispatched after an operation failed, with the categorized exception and its trace.
 */
final readonly class OperationFailed
{
    public function __construct(
        public Operation $operation,
        public ExecutionContext $context,
        public ExecutionException $exception,
    ) {}
}
