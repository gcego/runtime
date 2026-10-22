<?php

declare(strict_types=1);

namespace Gcego\Runtime\Rendering;

use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Runtime\Contracts\RendererInterface;
use Gcego\Runtime\Exceptions\RendererConflictException;
use Gcego\Runtime\Exceptions\RendererNotFoundException;
use Illuminate\Contracts\Container\Container;

/**
 * Maps component classes and interfaces to renderers. Separate from the
 * definition registry: gcego/registry finds definitions, this finds how to draw them.
 *
 * Precedence, for a component of class C:
 *  1. a binding for C itself;
 *  2. the binding of C's nearest parent class with inherit: true;
 *  3. the first registered inherit: true binding for an interface C implements.
 *
 * Registering a second renderer for the same target throws unless
 * override: true is passed. Renderers are built once through the container
 * (so they may use constructor injection) and must be stateless.
 */
final class RendererRegistry
{
    /**
     * @var array<class-string, RendererBinding>
     */
    private array $bindings = [];

    /**
     * @var list<array{target: class-string, previous: class-string, current: class-string}>
     */
    private array $overrides = [];

    /**
     * @var array<string, RendererBinding|false>
     */
    private array $resolvedBindings = [];

    /**
     * @var array<class-string, RendererInterface>
     */
    private array $instances = [];

    public function __construct(private readonly Container $container) {}

    /**
     * @param  class-string  $target  A ComponentInterface class or interface.
     * @param  class-string<RendererInterface>|RendererInterface  $renderer
     *
     * @throws RendererConflictException
     */
    public function register(string $target, string|RendererInterface $renderer, bool $inherit = true, bool $override = false): void
    {
        if (! (class_exists($target) || interface_exists($target)) || ! is_a($target, ComponentInterface::class, true)) {
            throw RendererConflictException::invalidTarget($target);
        }

        if (is_string($renderer) && (! class_exists($renderer) || ! is_a($renderer, RendererInterface::class, true))) {
            throw RendererConflictException::invalidRenderer($renderer);
        }

        $binding = new RendererBinding($target, $renderer, $inherit);
        $existing = $this->bindings[$target] ?? null;

        if ($existing !== null && ! $existing->sameAs($binding)) {
            if (! $override) {
                throw RendererConflictException::conflict($target, $existing->rendererClass(), $binding->rendererClass());
            }

            $this->overrides[] = ['target' => $target, 'previous' => $existing->rendererClass(), 'current' => $binding->rendererClass()];
        }

        $this->bindings[$target] = $binding;
        $this->resolvedBindings = [];
    }

    public function has(ComponentInterface|string $component): bool
    {
        return $this->bindingFor(is_string($component) ? $component : $component::class) !== null;
    }

    /**
     * @throws RendererNotFoundException
     */
    public function for(ComponentInterface $component, string $path = ''): RendererInterface
    {
        $binding = $this->bindingFor($component::class)
            ?? throw RendererNotFoundException::forComponent($component::class, $component->type(), $path);

        if (! is_string($binding->renderer)) {
            return $binding->renderer;
        }

        if (isset($this->instances[$binding->renderer])) {
            return $this->instances[$binding->renderer];
        }

        $instance = $this->container->make($binding->renderer);

        if (! $instance instanceof RendererInterface) {
            throw RendererConflictException::invalidRenderer($binding->renderer);
        }

        return $this->instances[$binding->renderer] = $instance;
    }

    /**
     * The binding that applies to a component class, following the precedence rules.
     */
    public function bindingFor(string $class): ?RendererBinding
    {
        if (! isset($this->resolvedBindings[$class])) {
            $this->resolvedBindings[$class] = $this->resolve($class) ?? false;
        }

        return $this->resolvedBindings[$class] ?: null;
    }

    /**
     * @return list<RendererBinding>
     */
    public function bindings(): array
    {
        return array_values($this->bindings);
    }

    /**
     * @return list<array{target: class-string, previous: class-string, current: class-string}>
     */
    public function overrides(): array
    {
        return $this->overrides;
    }

    private function resolve(string $class): ?RendererBinding
    {
        if (isset($this->bindings[$class])) {
            return $this->bindings[$class];
        }

        if (! class_exists($class)) {
            return null;
        }

        for ($parent = get_parent_class($class); $parent !== false; $parent = get_parent_class($parent)) {
            if (isset($this->bindings[$parent]) && $this->bindings[$parent]->inherit) {
                return $this->bindings[$parent];
            }
        }

        foreach ($this->bindings as $target => $binding) {
            if ($binding->inherit && interface_exists($target) && is_a($class, $target, true)) {
                return $binding;
            }
        }

        return null;
    }
}
