<?php

declare(strict_types=1);

namespace Gcego\Runtime\Security;

use Gcego\Registry\Index\DefinitionDescriptor;
use Gcego\Runtime\Contracts\RenderAuthorizer;
use Illuminate\Contracts\Auth\Access\Gate;

/**
 * Default authorizer: asks Laravel's Gate for the configured ability.
 *
 * Fails closed: until the application defines the ability, Laravel denies
 * it, so nothing is rendered through public entry points. Define it in a
 * service provider:
 *
 *     Gate::define('gcego-runtime.render', fn (?User $user, string $identity, DefinitionDescriptor $definition): bool => ...);
 *
 * The Gate resolves the current user per call; this class holds no user.
 */
final readonly class GateRenderAuthorizer implements RenderAuthorizer
{
    public function __construct(
        private Gate $gate,
        private string $ability,
    ) {}

    public function authorize(DefinitionDescriptor $descriptor): void
    {
        $this->gate->authorize($this->ability, [$descriptor->key(), $descriptor]);
    }
}
