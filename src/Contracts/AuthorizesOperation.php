<?php

declare(strict_types=1);

namespace Gcego\Runtime\Contracts;

use Gcego\Runtime\Execution\ExecutionRequest;

/**
 * A handler with its own authorization, checked after the ExecutionAuthorizer.
 *
 * Both must allow the operation. Returning false, or throwing, denies it.
 */
interface AuthorizesOperation
{
    public function authorize(ExecutionRequest $request): bool;
}
