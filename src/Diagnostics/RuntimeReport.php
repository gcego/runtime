<?php

declare(strict_types=1);

namespace Gcego\Runtime\Diagnostics;

/**
 * Machine-readable output of RuntimeInspector.
 */
final readonly class RuntimeReport
{
    /**
     * @param  list<RenderDiagnostic>  $diagnostics
     * @param  list<array{target: class-string, renderer: class-string, inherit: bool}>  $renderers
     * @param  list<array{path: string, type: string, class: class-string}>  $missingRenderers
     */
    public function __construct(
        public array $diagnostics,
        public array $renderers,
        public array $missingRenderers = [],
    ) {}

    /**
     * @return list<RenderDiagnostic>
     */
    public function check(string $check): array
    {
        return array_values(array_filter($this->diagnostics, static fn (RenderDiagnostic $diagnostic): bool => $diagnostic->check === $check));
    }

    public function isHealthy(): bool
    {
        foreach ($this->diagnostics as $diagnostic) {
            if ($diagnostic->level === RenderDiagnostic::Error) {
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
            'renderers' => $this->renderers,
            'missingRenderers' => $this->missingRenderers,
            'diagnostics' => array_map(static fn (RenderDiagnostic $diagnostic): array => $diagnostic->toArray(), $this->diagnostics),
        ];
    }
}
