<?php

declare(strict_types=1);

namespace Gcego\Runtime\Execution;

use Gcego\Registry\Contracts\RegistryInterface;
use Gcego\Registry\Exceptions\DefinitionNotFoundException;
use Gcego\Registry\Index\DefinitionDescriptor;
use Gcego\Runtime\Exceptions\InvalidOperationException;
use Gcego\Runtime\Exceptions\TargetNotFoundException;
use Gcego\Runtime\Support\Identifiers;

/**
 * Resolves operation targets through gcego/registry.
 *
 * Only registry identities are accepted, never class names or paths, and
 * resolution stops at the descriptor: the definition loads lazily, after
 * authorization.
 */
final readonly class DefinitionResolver
{
    public function __construct(private RegistryInterface $registry) {}

    /**
     * @throws InvalidOperationException|TargetNotFoundException
     */
    public function descriptor(string $identifier): DefinitionDescriptor
    {
        if (! Identifiers::isDefinition($identifier)) {
            throw InvalidOperationException::invalid('target', sprintf('"%s" is not a definition identifier.', mb_substr($identifier, 0, 100)));
        }

        try {
            return $this->registry->descriptor($identifier);
        } catch (DefinitionNotFoundException $exception) {
            throw TargetNotFoundException::forIdentifier($identifier, $exception);
        }
    }

    /**
     * @throws InvalidOperationException|TargetNotFoundException
     */
    public function target(string $identifier): ExecutionTarget
    {
        return new ExecutionTarget($this->descriptor($identifier), $this->registry);
    }
}
