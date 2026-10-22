<?php

declare(strict_types=1);

namespace Gcego\Runtime\Tests\Fixtures;

use Closure;
use Gcego\Runtime\Contracts\ExecutionMiddleware;
use Gcego\Runtime\Execution\ExecutionRequest;
use Gcego\Runtime\Execution\ExecutionResult;
use Illuminate\Contracts\Config\Repository;

/**
 * Records its position in the "fixtures.calls" config list, which every test starts empty.
 */
final readonly class RecordingMiddleware implements ExecutionMiddleware
{
    public function __construct(private Repository $config) {}

    public function handle(ExecutionRequest $request, Closure $next): ExecutionResult
    {
        $this->config->push('fixtures.calls', 'middleware:before');
        $result = $next($request);
        $this->config->push('fixtures.calls', 'middleware:after');

        return $result;
    }
}
