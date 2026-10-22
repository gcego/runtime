<?php

declare(strict_types=1);

namespace Gcego\Runtime\Diagnostics;

use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Components\Traversal\ComponentWalker;
use Gcego\Registry\Registry;
use Gcego\Runtime\Livewire\BlueprintHost;
use Gcego\Runtime\Rendering\RendererBinding;
use Gcego\Runtime\Rendering\RendererRegistry;
use Gcego\Runtime\RuntimeConfig;
use Illuminate\Contracts\View\Factory;
use Livewire\Livewire;
use Throwable;

/**
 * Explains the runtime: renderers, overrides, views, Livewire host and,
 * for a given definition, which components lack a renderer. Never throws.
 */
final readonly class RuntimeInspector
{
    public const array Views = [
        'gcego-runtime::renderers.blueprint',
        'gcego-runtime::renderers.container',
        'gcego-runtime::errors.component',
        'gcego-runtime::hosts.blueprint',
        'gcego-runtime::components.render',
    ];

    public function __construct(
        private RendererRegistry $renderers,
        private RuntimeConfig $config,
        private Factory $views,
        private Registry $registry,
    ) {}

    public function inspect(ComponentInterface|string|null $target = null): RuntimeReport
    {
        $diagnostics = [];

        $diagnostics[] = new RenderDiagnostic('config', RenderDiagnostic::Ok, 'Runtime configuration loaded.', [
            'strict' => $this->config->strict,
            'validate' => $this->config->validate,
            'maxDepth' => $this->config->maxDepth,
            'livewire' => $this->config->livewireEnabled,
            'ability' => $this->config->ability,
        ]);

        foreach ($this->renderers->overrides() as $override) {
            $diagnostics[] = new RenderDiagnostic('renderers.override', RenderDiagnostic::Warning, sprintf('%s replaced %s for %s.', $override['current'], $override['previous'], $override['target']), $override);
        }

        foreach (self::Views as $view) {
            $exists = $this->views->exists($view);
            $diagnostics[] = new RenderDiagnostic('views', $exists ? RenderDiagnostic::Ok : RenderDiagnostic::Error, sprintf('View [%s] %s.', $view, $exists ? 'is available' : 'is missing'), ['view' => $view]);
        }

        $diagnostics[] = $this->livewire();
        $missing = [];

        if ($target !== null) {
            [$diagnostics, $missing] = $this->inspectTarget($target, $diagnostics);
        }

        return new RuntimeReport(
            $diagnostics,
            array_map(static fn (RendererBinding $binding): array => $binding->toArray(), $this->renderers->bindings()),
            $missing,
        );
    }

    private function livewire(): RenderDiagnostic
    {
        if (! $this->config->livewireEnabled) {
            return new RenderDiagnostic('livewire', RenderDiagnostic::Ok, 'The Livewire host is disabled.');
        }

        return Livewire::exists($this->config->livewireAlias)
            ? new RenderDiagnostic('livewire', RenderDiagnostic::Ok, sprintf('<livewire:%s> is registered.', $this->config->livewireAlias), ['alias' => $this->config->livewireAlias])
            : new RenderDiagnostic('livewire', RenderDiagnostic::Error, sprintf('<livewire:%s> is not registered to %s.', $this->config->livewireAlias, BlueprintHost::class), ['alias' => $this->config->livewireAlias]);
    }

    /**
     * @param  list<RenderDiagnostic>  $diagnostics
     * @return array{0: list<RenderDiagnostic>, 1: list<array{path: string, type: string, class: class-string}>}
     */
    private function inspectTarget(ComponentInterface|string $target, array $diagnostics): array
    {
        $missing = [];

        try {
            $component = is_string($target) ? $this->registry->get($target) : $target;

            if (! $component instanceof ComponentInterface) {
                $diagnostics[] = new RenderDiagnostic('target', RenderDiagnostic::Error, sprintf('%s is not a component.', $component::class));

                return [$diagnostics, $missing];
            }

            $count = 0;

            foreach ((new ComponentWalker($this->config->maxDepth))->preOrder($component) as $node) {
                $count++;

                if (! $this->renderers->has($node->component)) {
                    $missing[] = ['path' => $node->path->toString(), 'type' => $node->component->type(), 'class' => $node->component::class];
                }
            }

            $validation = $component->validate();

            $diagnostics[] = new RenderDiagnostic(
                'target.renderers',
                $missing === [] ? RenderDiagnostic::Ok : RenderDiagnostic::Error,
                $missing === [] ? sprintf('All %d component(s) have renderers.', $count) : sprintf('%d of %d component(s) have no renderer.', count($missing), $count),
                ['components' => $count, 'missing' => count($missing)],
            );
            $diagnostics[] = new RenderDiagnostic(
                'target.validation',
                $validation->isValid() ? RenderDiagnostic::Ok : RenderDiagnostic::Error,
                $validation->isValid() ? 'The definition is valid.' : sprintf('The definition has %d validation error(s).', $validation->count()),
            );
        } catch (Throwable $exception) {
            $diagnostics[] = new RenderDiagnostic('target', RenderDiagnostic::Error, $exception->getMessage(), ['exception' => $exception::class]);
        }

        return [$diagnostics, $missing];
    }
}
