<?php

declare(strict_types=1);

namespace Gcego\Runtime\Execution;

use Gcego\Blueprints\Contracts\BlueprintInterface;
use Gcego\Blueprints\Registry\BlueprintKind;
use Gcego\Registry\Contracts\RegistryInterface;
use Gcego\Registry\Index\DefinitionDescriptor;
use Gcego\Runtime\Exceptions\InvalidOperationException;

/**
 * The registered definition an operation acts on.
 *
 * The descriptor comes from the registry index, so authorization can run on
 * it before any definition code is loaded. definition() loads the object on
 * first use, after authorization.
 */
final class ExecutionTarget
{
    private ?object $definition = null;

    public function __construct(
        public readonly DefinitionDescriptor $descriptor,
        private readonly RegistryInterface $registry,
    ) {}

    /**
     * The canonical identity, for example "blueprint:subjects".
     */
    public function identity(): string
    {
        return $this->descriptor->key();
    }

    public function kind(): string
    {
        return $this->descriptor->kind();
    }

    public function isBlueprint(): bool
    {
        return $this->descriptor->kind() === BlueprintKind::Id;
    }

    public function isLoaded(): bool
    {
        return $this->definition !== null;
    }

    public function definition(): object
    {
        return $this->definition ??= $this->registry->get($this->descriptor->key());
    }

    /**
     * The definition, checked against a class or interface.
     *
     * @template T of object
     *
     * @param  class-string<T>  $expected
     * @return T
     *
     * @throws InvalidOperationException
     */
    public function definitionAs(string $expected): object
    {
        $definition = $this->definition();

        if (! $definition instanceof $expected) {
            throw InvalidOperationException::unexpectedTarget($this->identity(), $expected, $definition::class);
        }

        return $definition;
    }

    /**
     * @throws InvalidOperationException when the target is not a blueprint.
     */
    public function blueprint(): BlueprintInterface
    {
        return $this->definitionAs(BlueprintInterface::class);
    }
}
