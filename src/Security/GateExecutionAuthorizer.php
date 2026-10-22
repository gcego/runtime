<?php

declare(strict_types=1);

namespace Gcego\Runtime\Security;

use Gcego\Runtime\Contracts\ExecutionAuthorizer;
use Gcego\Runtime\Execution\ExecutionRequest;
use Illuminate\Contracts\Auth\Access\Gate;

/**
 * Default execution authorizer: asks Laravel's Gate for the configured
 * ability, as the context's user (not whoever is signed in).
 *
 * Fails closed: until the application defines the ability, Laravel denies
 * it, so no operation runs. System contexts (trusted server code) are allowed.
 *
 *     Gate::define('gcego-runtime.execute', fn (?User $user, ExecutionRequest $request): bool => ...);
 */
final readonly class GateExecutionAuthorizer implements ExecutionAuthorizer
{
    public function __construct(
        private Gate $gate,
        private string $ability,
    ) {}

    public function authorize(ExecutionRequest $request): void
    {
        if ($request->context->isSystem()) {
            return;
        }

        $this->gate->forUser($request->context->user)->authorize($this->ability, [$request]);
    }
}
