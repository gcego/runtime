<?php

declare(strict_types=1);

namespace Gcego\Runtime\Execution;

use Closure;
use Gcego\Runtime\Exceptions\ErrorCategory;

/**
 * Diagnostic record of one execution: stages, timings and outcome.
 *
 * Holds identifiers only (operation, target, context request id), never
 * input, user objects or results, so it is safe to log.
 */
final class ExecutionTrace
{
    public readonly string $id;

    /**
     * @var list<array{stage: string, ms: float, ok: bool}>
     */
    private array $stages = [];

    private ?ResultStatus $status = null;

    private ?ErrorCategory $category = null;

    private ?string $handler = null;

    private readonly float $started;

    private ?float $durationMs = null;

    public function __construct(
        public readonly Operation $operation,
        public readonly string $requestId,
        public readonly int $depth = 0,
    ) {
        $this->id = bin2hex(random_bytes(8));
        $this->started = hrtime(true) / 1e6;
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function measure(string $stage, Closure $callback): mixed
    {
        $start = hrtime(true);
        $ok = false;

        try {
            $result = $callback();
            $ok = true;

            return $result;
        } finally {
            $this->stages[] = ['stage' => $stage, 'ms' => round((hrtime(true) - $start) / 1e6, 3), 'ok' => $ok];
        }
    }

    /**
     * @internal
     */
    public function handledBy(string $handler): void
    {
        $this->handler = $handler;
    }

    /**
     * @internal
     */
    public function finish(ResultStatus $status, ?ErrorCategory $category = null): void
    {
        if ($this->status !== null) {
            return;
        }

        $this->status = $status;
        $this->category = $category;
        $this->durationMs = round(hrtime(true) / 1e6 - $this->started, 3);
    }

    public function status(): ?ResultStatus
    {
        return $this->status;
    }

    public function category(): ?ErrorCategory
    {
        return $this->category;
    }

    public function handler(): ?string
    {
        return $this->handler;
    }

    public function durationMs(): ?float
    {
        return $this->durationMs;
    }

    /**
     * @return list<array{stage: string, ms: float, ok: bool}>
     */
    public function stages(): array
    {
        return $this->stages;
    }

    /**
     * @return list<string>
     */
    public function stageNames(): array
    {
        return array_column($this->stages, 'stage');
    }

    /**
     * @return array{id: string, operation: array{type: string, name: string, target: string|null}, request: string, depth: int, handler: string|null, status: string|null, category: string|null, duration_ms: float|null, stages: list<array{stage: string, ms: float, ok: bool}>}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'operation' => $this->operation->toArray(),
            'request' => $this->requestId,
            'depth' => $this->depth,
            'handler' => $this->handler,
            'status' => $this->status?->value,
            'category' => $this->category?->value,
            'duration_ms' => $this->durationMs,
            'stages' => $this->stages,
        ];
    }
}
