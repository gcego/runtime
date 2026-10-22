<?php

declare(strict_types=1);

namespace Gcego\Runtime\Tests\Fixtures;

use Gcego\Core\Extensions\ExtensionDescriptor;
use Gcego\Runtime\Execution\ExecutionPipeline;
use Gcego\Runtime\Execution\HandlerRegistry;
use Gcego\Runtime\Execution\Operation;
use Gcego\Runtime\Extensions\ExecutionExtension;

/**
 * How gcego/data (or an application) plugs query execution into the runtime
 * through gcego/core's extension system, without the runtime importing it.
 */
final class DataQueriesExtension extends ExecutionExtension
{
    public function descriptor(): ExtensionDescriptor
    {
        return new ExtensionDescriptor('fixtures.data-queries', whenPackages: ['gcego/runtime'], capabilities: ['fixtures.data-queries']);
    }

    protected function extend(HandlerRegistry $handlers, ExecutionPipeline $pipeline): void
    {
        $handlers->register(Operation::Query, FakeDataSource::class, name: 'students');
        $pipeline->middleware(RecordingMiddleware::class);
    }
}
