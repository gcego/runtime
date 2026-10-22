<?php

declare(strict_types=1);

namespace Gcego\Runtime\Contracts;

use Gcego\Runtime\Execution\ExecutionRequest;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;

/**
 * Decides whether the context may execute an operation. Runs for every
 * operation, before validation, and before the target definition is loaded:
 * use $request->target?->descriptor, not definition().
 *
 * Throw AuthorizationException to deny. Any other exception also denies the
 * operation (fails closed). gcego/security replaces the Gate-based default.
 */
interface ExecutionAuthorizer
{
    /**
     * @throws AuthorizationException|AuthenticationException
     */
    public function authorize(ExecutionRequest $request): void;
}
