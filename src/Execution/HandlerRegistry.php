<?php

declare(strict_types=1);

namespace Gcego\Runtime\Execution;

use Gcego\Runtime\Contracts\ActionHandler;
use Gcego\Runtime\Contracts\FormSubmissionHandler;
use Gcego\Runtime\Contracts\OperationHandler;
use Gcego\Runtime\Contracts\QueryHandler;
use Gcego\Runtime\Exceptions\HandlerRegistrationException;
use Gcego\Runtime\Exceptions\OperationNotSupportedException;
use Gcego\Runtime\Support\Identifiers;
use Illuminate\Contracts\Container\Container;

/**
 * Maps operation categories to typed handler contracts, and operations to handlers.
 *
 * Each category requires a contract: an "action" handler must implement
 * ActionHandler, a "query" handler QueryHandler, a "form" handler
 * FormSubmissionHandler. Packages add categories with category(), for
 * example gcego/rendering declares "render".
 *
 * For an operation of type T named N, the handler registered for (T, N)
 * wins over the one registered for T alone. Registering a second handler
 * for the same key throws unless override: true is passed. Handlers are
 * built once through the container and must be stateless.
 */
final class HandlerRegistry
{
    /**
     * @var array<string, class-string<OperationHandler>>
     */
    private array $categories = [
        Operation::Action => ActionHandler::class,
        Operation::Query => QueryHandler::class,
        Operation::FormSubmission => FormSubmissionHandler::class,
    ];

    /**
     * @var array<string, HandlerBinding>
     */
    private array $bindings = [];

    /**
     * @var list<array{key: string, previous: class-string, current: class-string}>
     */
    private array $overrides = [];

    /**
     * @var array<class-string, OperationHandler>
     */
    private array $instances = [];

    public function __construct(private readonly Container $container) {}

    /**
     * Declare an operation category and the handler contract it requires.
     *
     * @param  class-string<OperationHandler>  $contract  An interface extending OperationHandler.
     *
     * @throws HandlerRegistrationException
     */
    public function category(string $type, string $contract = OperationHandler::class): void
    {
        if (! Identifiers::isName($type)) {
            throw HandlerRegistrationException::invalidName('operation type', $type);
        }

        if (! interface_exists($contract) || ! is_a($contract, OperationHandler::class, true)) {
            throw HandlerRegistrationException::invalidContract($contract);
        }

        $existing = $this->categories[$type] ?? null;

        if ($existing !== null && $existing !== $contract) {
            throw HandlerRegistrationException::categoryConflict($type, $existing, $contract);
        }

        $this->categories[$type] = $contract;
    }

    /**
     * @param  class-string<OperationHandler>|OperationHandler  $handler
     * @param  string|null  $name  Handle only operations with this name; null handles the whole category.
     *
     * @throws HandlerRegistrationException
     */
    public function register(string $type, string|OperationHandler $handler, ?string $name = null, bool $override = false): void
    {
        $contract = $this->categories[$type] ?? throw HandlerRegistrationException::unknownCategory($type);

        if ($name !== null && ! Identifiers::isName($name)) {
            throw HandlerRegistrationException::invalidName('operation name', $name);
        }

        if (is_string($handler) && (! class_exists($handler) || ! is_a($handler, $contract, true))) {
            throw HandlerRegistrationException::invalidHandler($handler, $contract);
        }

        if (! $handler instanceof $contract && ! is_string($handler)) {
            throw HandlerRegistrationException::invalidHandler($handler::class, $contract);
        }

        $binding = new HandlerBinding($type, $name, $handler);
        $existing = $this->bindings[$binding->key()] ?? null;

        if ($existing !== null && ! $existing->sameAs($binding)) {
            if (! $override) {
                throw HandlerRegistrationException::conflict($binding->key(), $existing->handlerClass(), $binding->handlerClass());
            }

            $this->overrides[] = ['key' => $binding->key(), 'previous' => $existing->handlerClass(), 'current' => $binding->handlerClass()];
        }

        $this->bindings[$binding->key()] = $binding;
    }

    public function hasCategory(string $type): bool
    {
        return isset($this->categories[$type]);
    }

    public function has(Operation $operation): bool
    {
        return $this->bindingFor($operation) !== null;
    }

    public function bindingFor(Operation $operation): ?HandlerBinding
    {
        return $this->bindings[$operation->type.':'.$operation->name] ?? $this->bindings[$operation->type.':*'] ?? null;
    }

    /**
     * @throws OperationNotSupportedException
     */
    public function for(Operation $operation): OperationHandler
    {
        if (! isset($this->categories[$operation->type])) {
            throw OperationNotSupportedException::unknownType($operation->type);
        }

        $binding = $this->bindingFor($operation) ?? throw OperationNotSupportedException::noHandler($operation->type, $operation->name);

        if (! is_string($binding->handler)) {
            return $binding->handler;
        }

        if (isset($this->instances[$binding->handler])) {
            return $this->instances[$binding->handler];
        }

        $instance = $this->container->make($binding->handler);
        $contract = $this->categories[$operation->type];

        if (! $instance instanceof $contract) {
            throw HandlerRegistrationException::invalidHandler($binding->handler, $contract);
        }

        return $this->instances[$binding->handler] = $instance;
    }

    /**
     * @return array<string, class-string<OperationHandler>>
     */
    public function categories(): array
    {
        return $this->categories;
    }

    /**
     * @return list<HandlerBinding>
     */
    public function bindings(): array
    {
        return array_values($this->bindings);
    }

    /**
     * @return list<array{key: string, previous: class-string, current: class-string}>
     */
    public function overrides(): array
    {
        return $this->overrides;
    }
}
