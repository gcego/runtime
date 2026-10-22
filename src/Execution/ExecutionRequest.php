<?php

declare(strict_types=1);

namespace Gcego\Runtime\Execution;

use Gcego\Runtime\Context\ExecutionContext;
use Gcego\Runtime\Exceptions\InvalidOperationException;

/**
 * Everything a handler, authorizer or middleware receives about one execution.
 */
final readonly class ExecutionRequest
{
    /**
     * @param  array<string, mixed>|null  $validated  The input that passed the handler's rules; null before validation.
     */
    public function __construct(
        public Operation $operation,
        public ExecutionContext $context,
        public ?ExecutionTarget $target,
        public ExecutionTrace $trace,
        public ?array $validated = null,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public function withValidated(array $validated): self
    {
        return new self($this->operation, $this->context, $this->target, $this->trace, $validated);
    }

    /**
     * The target, which this operation must have.
     *
     * @throws InvalidOperationException
     */
    public function target(): ExecutionTarget
    {
        return $this->target ?? throw InvalidOperationException::targetRequired($this->operation->type);
    }

    /**
     * Validated input when the handler declared rules, otherwise the raw input.
     *
     * Only fields covered by the rules are returned after validation.
     */
    public function input(?string $key = null, mixed $default = null): mixed
    {
        $input = $this->validated ?? $this->operation->input;

        return $key === null ? $input : data_get($input, $key, $default);
    }

    public function isValidated(): bool
    {
        return $this->validated !== null;
    }
}
