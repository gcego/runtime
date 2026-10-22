<?php

declare(strict_types=1);

namespace Gcego\Runtime\Execution;

use Gcego\Runtime\Context\ExecutionContext;
use Gcego\Runtime\Exceptions\ContextViolationException;

/**
 * The executions in progress for the current request, job or command.
 *
 * Bound with $app->scoped(), so Laravel discards it between requests and
 * queued jobs (including under Octane), and the kernel pops every entry in
 * a finally block. It is never injected into singletons; the kernel resolves
 * it from the container on each call.
 *
 * Nested operations (started by a handler) must keep the subject of their
 * parent: same user, same tenant, same privilege. Only a system context may
 * start operations for another subject.
 */
final class ExecutionScope
{
    /**
     * @var list<ExecutionContext>
     */
    private array $stack = [];

    /**
     * @throws ContextViolationException
     */
    public function enter(ExecutionContext $context, Operation $operation, int $maxDepth): void
    {
        $parent = $this->current();

        if (count($this->stack) >= $maxDepth) {
            throw ContextViolationException::tooDeep($operation->key(), $maxDepth);
        }

        if ($parent !== null && ! $parent->isSystem() && ! $parent->sameSubjectAs($context)) {
            throw ContextViolationException::switchedSubject($operation->key());
        }

        $this->stack[] = $context;
    }

    public function leave(): void
    {
        array_pop($this->stack);
    }

    public function current(): ?ExecutionContext
    {
        return $this->stack === [] ? null : $this->stack[array_key_last($this->stack)];
    }

    public function depth(): int
    {
        return count($this->stack);
    }

    public function isActive(): bool
    {
        return $this->stack !== [];
    }
}
