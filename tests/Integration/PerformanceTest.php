<?php

declare(strict_types=1);

use Gcego\Runtime\Context\ExecutionContext;
use Gcego\Runtime\Execution\HandlerRegistry;
use Gcego\Runtime\Execution\Operation;
use Gcego\Runtime\Runtime;
use Gcego\Runtime\Tests\Fixtures\GenericHandler;

beforeEach(function (): void {
    app(HandlerRegistry::class)->category('benchmark');
    app(HandlerRegistry::class)->register('benchmark', GenericHandler::class);
});

it('executes thousands of operations quickly', function (): void {
    $runtime = app(Runtime::class);
    $context = ExecutionContext::system();
    $operation = Operation::make('benchmark', 'noop');
    $start = hrtime(true);

    for ($run = 0; $run < 2000; $run++) {
        $runtime->execute($operation, $context);
    }

    // Regression guard, not a benchmark (see benchmarks/run.php).
    expect((hrtime(true) - $start) / 1e6)->toBeLessThan(5000.0);
});

it('keeps memory stable across repeated executions', function (): void {
    $runtime = app(Runtime::class);
    $context = ExecutionContext::system();
    $operation = Operation::make('benchmark', 'noop');
    $runtime->execute($operation, $context);
    gc_collect_cycles();
    $before = memory_get_usage();

    for ($run = 0; $run < 500; $run++) {
        $runtime->execute($operation, $context);
    }

    gc_collect_cycles();

    expect(memory_get_usage() - $before)->toBeLessThan(1024 * 1024);
});
