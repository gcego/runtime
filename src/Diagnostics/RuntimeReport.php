<?php

declare(strict_types=1);

namespace Gcego\Runtime\Diagnostics;

/**
 * Machine-readable output of RuntimeInspector.
 */
final readonly class RuntimeReport
{
    /**
     * @param  list<RuntimeDiagnostic>  $diagnostics
     * @param  list<array{type: string, name: string|null, handler: class-string}>  $handlers
     */
    public function __construct(
        public array $diagnostics,
        public array $handlers = [],
    ) {}

    /**
     * @return list<RuntimeDiagnostic>
     */
    public function check(string $check): array
    {
        return array_values(array_filter($this->diagnostics, static fn (RuntimeDiagnostic $diagnostic): bool => $diagnostic->check === $check));
    }

    public function isHealthy(): bool
    {
        foreach ($this->diagnostics as $diagnostic) {
            if ($diagnostic->level === RuntimeDiagnostic::Error) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'healthy' => $this->isHealthy(),
            'handlers' => $this->handlers,
            'diagnostics' => array_map(static fn (RuntimeDiagnostic $diagnostic): array => $diagnostic->toArray(), $this->diagnostics),
        ];
    }
}
