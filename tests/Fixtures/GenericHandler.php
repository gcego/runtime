<?php

declare(strict_types=1);

namespace Gcego\Runtime\Tests\Fixtures;

use Gcego\Runtime\Contracts\OperationHandler;
use Gcego\Runtime\Execution\ExecutionRequest;

/**
 * A handler for a custom category that only requires OperationHandler.
 */
final class GenericHandler implements OperationHandler
{
    public function handle(ExecutionRequest $request): string
    {
        return $request->operation->key();
    }
}
