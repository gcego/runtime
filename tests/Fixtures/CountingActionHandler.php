<?php

declare(strict_types=1);

namespace Gcego\Runtime\Tests\Fixtures;

use Gcego\Runtime\Contracts\ActionHandler;
use Gcego\Runtime\Execution\ExecutionRequest;

/**
 * Counts its instances, to prove handlers are built once.
 */
final class CountingActionHandler implements ActionHandler
{
    public static int $instances = 0;

    public function __construct()
    {
        self::$instances++;
    }

    public function handle(ExecutionRequest $request): string
    {
        return $request->operation->name;
    }
}
