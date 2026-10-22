<?php

declare(strict_types=1);

namespace Gcego\Runtime\Extensions;

use Gcego\Core\Bootstrap\BootstrapContext;
use Gcego\Core\Contracts\ExtensionInterface;
use Gcego\Runtime\Execution\ExecutionPipeline;
use Gcego\Runtime\Execution\HandlerRegistry;

/**
 * Base class for gcego/core extensions that add execution capabilities:
 * operation categories, handlers and middleware.
 *
 * Register it with Core's ExtensionManager in your provider's register().
 * Core activates it once, after every package has booted, and skips it when
 * a package in whenPackages is inactive:
 *
 *     final class DataQueries extends ExecutionExtension
 *     {
 *         public function descriptor(): ExtensionDescriptor
 *         {
 *             return new ExtensionDescriptor('data.queries', whenPackages: ['gcego/runtime', 'gcego/data'], capabilities: ['data.queries']);
 *         }
 *
 *         protected function extend(HandlerRegistry $handlers, ExecutionPipeline $pipeline): void
 *         {
 *             $handlers->register(Operation::Query, DataQueryHandler::class);
 *         }
 *     }
 */
abstract class ExecutionExtension implements ExtensionInterface
{
    final public function activate(BootstrapContext $context): void
    {
        $this->extend($context->app->make(HandlerRegistry::class), $context->app->make(ExecutionPipeline::class));
    }

    abstract protected function extend(HandlerRegistry $handlers, ExecutionPipeline $pipeline): void;
}
