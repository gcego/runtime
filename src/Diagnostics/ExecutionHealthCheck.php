<?php

declare(strict_types=1);

namespace Gcego\Runtime\Diagnostics;

use Gcego\Core\Contracts\DiagnosticCheck;
use Gcego\Core\Diagnostics\DiagnosticResult;
use Gcego\Runtime\Contracts\ExecutionAuthorizer;
use Gcego\Runtime\Execution\ExecutionPipeline;
use Gcego\Runtime\Execution\HandlerRegistry;
use Illuminate\Contracts\Container\Container;

/**
 * Contributes the runtime's execution health to "php artisan gcego:diagnose".
 */
final readonly class ExecutionHealthCheck implements DiagnosticCheck
{
    public const string Id = 'gcego-runtime.execution';

    public function __construct(private Container $container) {}

    public function id(): string
    {
        return self::Id;
    }

    public function run(): DiagnosticResult
    {
        $handlers = $this->container->make(HandlerRegistry::class);
        $pipeline = $this->container->make(ExecutionPipeline::class);
        $authorizer = $this->container->make(ExecutionAuthorizer::class);

        $handled = array_unique(array_map(static fn ($binding): string => $binding->type, $handlers->bindings()));
        $unhandled = array_values(array_diff(array_keys($handlers->categories()), $handled));
        sort($unhandled);

        $details = [
            'handlers' => count($handlers->bindings()),
            'categories' => array_keys($handlers->categories()),
            'unhandled_categories' => $unhandled,
            'middleware' => count($pipeline->middlewareClasses()),
            'overrides' => count($handlers->overrides()),
            'authorizer' => $authorizer::class,
        ];

        if ($handlers->overrides() !== []) {
            return DiagnosticResult::warning(self::Id, 'Execution handlers were replaced with override: true.', $details, 'Confirm every handler override is intended.', 'gcego/runtime');
        }

        return DiagnosticResult::passed(self::Id, sprintf('%d execution handler(s) registered.', count($handlers->bindings())), $details, package: 'gcego/runtime');
    }
}
