<?php

declare(strict_types=1);

namespace Gcego\Runtime\Execution;

use Gcego\Runtime\Exceptions\ErrorCategory;
use Gcego\Runtime\Exceptions\ExecutionException;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * @implements Arrayable<string, mixed>
 *
 * The standardized outcome of an executed operation.
 *
 * execute() returns successful results and throws ExecutionException on
 * failure; attempt() returns failures as results too.
 */
final readonly class ExecutionResult implements Arrayable, JsonSerializable
{
    /**
     * @param  array<string, scalar|list<scalar>|null>  $meta
     */
    private function __construct(
        public ResultStatus $status,
        public mixed $data,
        public ?ExecutionException $error,
        public array $meta,
        public ?ExecutionTrace $trace,
    ) {}

    /**
     * @param  array<string, scalar|list<scalar>|null>  $meta
     */
    public static function success(mixed $data = null, array $meta = []): self
    {
        return new self(ResultStatus::Succeeded, $data, null, $meta, null);
    }

    public static function failure(ExecutionException $error): self
    {
        return new self(ResultStatus::Failed, null, $error, [], $error->trace());
    }

    /**
     * @internal Called by the execution kernel.
     */
    public function withTrace(ExecutionTrace $trace): self
    {
        return new self($this->status, $this->data, $this->error, $this->meta, $trace);
    }

    public function succeeded(): bool
    {
        return $this->status === ResultStatus::Succeeded;
    }

    public function failed(): bool
    {
        return $this->status === ResultStatus::Failed;
    }

    public function data(): mixed
    {
        return $this->data;
    }

    public function category(): ?ErrorCategory
    {
        return $this->error?->category;
    }

    /**
     * Rethrow the failure, if any.
     *
     * @throws ExecutionException
     */
    public function throw(): self
    {
        if ($this->error !== null) {
            throw $this->error;
        }

        return $this;
    }

    /**
     * Safe for API responses: failures expose only the category's public message and validation errors.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'status' => $this->status->value,
            'data' => $this->data instanceof Arrayable ? $this->data->toArray() : ($this->data instanceof JsonSerializable || is_scalar($this->data) || is_array($this->data) ? $this->data : null),
            'error' => $this->error?->toArray(),
            'meta' => $this->meta === [] ? null : $this->meta,
            'trace' => $this->trace?->id,
        ], static fn (mixed $value): bool => $value !== null);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
