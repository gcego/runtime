<?php

declare(strict_types=1);

namespace Gcego\Runtime\Execution;

use Gcego\Runtime\Contracts\OperationHandler;

/**
 * One handler registration: a category, an optional operation name, and the handler.
 */
final readonly class HandlerBinding
{
    /**
     * @param  class-string<OperationHandler>|OperationHandler  $handler
     * @param  string|null  $name  null handles every operation of the type without a more specific handler.
     */
    public function __construct(
        public string $type,
        public ?string $name,
        public string|OperationHandler $handler,
    ) {}

    public function key(): string
    {
        return $this->type.':'.($this->name ?? '*');
    }

    /**
     * @return class-string<OperationHandler>
     */
    public function handlerClass(): string
    {
        return is_string($this->handler) ? $this->handler : $this->handler::class;
    }

    public function sameAs(self $other): bool
    {
        return $this->key() === $other->key() && $this->handler === $other->handler;
    }

    /**
     * @return array{type: string, name: string|null, handler: class-string<OperationHandler>}
     */
    public function toArray(): array
    {
        return ['type' => $this->type, 'name' => $this->name, 'handler' => $this->handlerClass()];
    }
}
