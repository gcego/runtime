<?php

declare(strict_types=1);

namespace Gcego\Runtime\Diagnostics;

use Gcego\Core\Contracts\FrameworkInterface;
use Gcego\Runtime\Contracts\ExecutionAuthorizer;
use Gcego\Runtime\Execution\ExecutionPipeline;
use Gcego\Runtime\Execution\HandlerBinding;
use Gcego\Runtime\Execution\HandlerRegistry;
use Gcego\Runtime\RuntimeConfig;
use Illuminate\Contracts\Container\Container;
use Throwable;

/**
 * Explains the execution kernel: configuration, framework state, categories
 * and their handlers, overrides, the authorizer and middleware. Never throws.
 */
final readonly class RuntimeInspector
{
    public function __construct(
        private HandlerRegistry $handlers,
        private ExecutionPipeline $pipeline,
        private RuntimeConfig $config,
        private Container $container,
    ) {}

    public function inspect(): RuntimeReport
    {
        $diagnostics = [new RuntimeDiagnostic('config', RuntimeDiagnostic::Ok, 'Runtime configuration loaded.', [
            'ability' => $this->config->ability,
            'maxNesting' => $this->config->maxNesting,
        ])];

        try {
            $booted = $this->container->bound(FrameworkInterface::class) && $this->container->make(FrameworkInterface::class)->isBooted();
            $diagnostics[] = $booted
                ? new RuntimeDiagnostic('execution.framework', RuntimeDiagnostic::Ok, 'GCEGO Studio has booted; operations can execute.')
                : new RuntimeDiagnostic('execution.framework', RuntimeDiagnostic::Error, 'GCEGO Studio has not booted; execute() refuses to run.');

            $handled = array_map(static fn (HandlerBinding $binding): string => $binding->type, $this->handlers->bindings());

            foreach ($this->handlers->categories() as $type => $contract) {
                $diagnostics[] = in_array($type, $handled, true)
                    ? new RuntimeDiagnostic('execution.categories', RuntimeDiagnostic::Ok, sprintf('"%s" operations have a handler.', $type), ['type' => $type, 'contract' => $contract])
                    : new RuntimeDiagnostic('execution.categories', RuntimeDiagnostic::Warning, sprintf('"%s" operations have no handler yet (%s).', $type, $contract), ['type' => $type, 'contract' => $contract]);
            }

            foreach ($this->handlers->overrides() as $override) {
                $diagnostics[] = new RuntimeDiagnostic('execution.override', RuntimeDiagnostic::Warning, sprintf('%s replaced %s for %s.', $override['current'], $override['previous'], $override['key']), $override);
            }

            $diagnostics[] = new RuntimeDiagnostic('execution.authorizer', RuntimeDiagnostic::Ok, sprintf('Operations are authorized by %s.', $this->container->make(ExecutionAuthorizer::class)::class));
            $diagnostics[] = new RuntimeDiagnostic('execution.middleware', RuntimeDiagnostic::Ok, sprintf('%d execution middleware registered.', count($this->pipeline->middlewareClasses())), ['middleware' => $this->pipeline->middlewareClasses()]);
        } catch (Throwable $exception) {
            $diagnostics[] = new RuntimeDiagnostic('execution', RuntimeDiagnostic::Error, $exception->getMessage(), ['exception' => $exception::class]);
        }

        return new RuntimeReport(
            $diagnostics,
            array_map(static fn (HandlerBinding $binding): array => $binding->toArray(), $this->handlers->bindings()),
        );
    }
}
