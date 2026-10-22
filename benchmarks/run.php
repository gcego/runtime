<?php

declare(strict_types=1);

/**
 * Repeatable benchmark for gcego/runtime in a booted Testbench application.
 *
 * Usage: composer benchmark  (or: php benchmarks/run.php [iterations])
 *
 * Measures handler lookup, kernel overhead per operation (with and without
 * validation and middleware) and memory growth.
 */

use Gcego\Blueprints\BlueprintsServiceProvider;
use Gcego\Core\GcegoServiceProvider;
use Gcego\Registry\RegistryServiceProvider;
use Gcego\Runtime\Context\ExecutionContext;
use Gcego\Runtime\Execution\ExecutionPipeline;
use Gcego\Runtime\Execution\HandlerRegistry;
use Gcego\Runtime\Execution\Operation;
use Gcego\Runtime\Runtime;
use Gcego\Runtime\RuntimeServiceProvider;
use Gcego\Runtime\Tests\Fixtures\ArchiveHandler;
use Gcego\Runtime\Tests\Fixtures\GenericHandler;
use Gcego\Runtime\Tests\Fixtures\RecordingMiddleware;
use Orchestra\Testbench\Foundation\Application;

require __DIR__.'/../vendor/autoload.php';

$iterations = max(1, (int) ($argv[1] ?? 20));

$app = Application::create(options: [
    'extra' => [
        'providers' => [GcegoServiceProvider::class, RegistryServiceProvider::class, BlueprintsServiceProvider::class, RuntimeServiceProvider::class],
        'dont-discover' => ['*'],
    ],
    'load_environment_variables' => false,
]);
$app['config']->set('gcego-registry.discovery.enabled', false);
$app['config']->set('gcego-registry.cache.enabled', false);
$app['config']->set('gcego-blueprints.discovery.enabled', false);
$app['config']->set('fixtures.calls', []);

$handlers = $app->make(HandlerRegistry::class);
$handlers->category('benchmark');
$handlers->register('benchmark', GenericHandler::class);
$handlers->register(Operation::Action, ArchiveHandler::class, name: 'archive');
$runtime = $app->make(Runtime::class);
$system = ExecutionContext::system();
$noop = Operation::make('benchmark', 'noop');
$archive = Operation::action('archive')->with(['record' => 5]);

$measure = static function (string $label, Closure $operation, int $operations) use ($iterations): void {
    $start = hrtime(true);

    for ($iteration = 0; $iteration < $iterations; $iteration++) {
        $operation();
    }

    $elapsed = (hrtime(true) - $start) / 1e6 / $iterations;
    printf("%-40s %9.3f ms per %d (%.3f us each)\n", $label, $elapsed, $operations, $elapsed * 1000 / $operations);
};

printf("gcego/runtime benchmark: %d iterations, PHP %s, Laravel %s\n\n", $iterations, PHP_VERSION, $app->version());

$measure('handler lookup', static function () use ($handlers, $noop): void {
    for ($lookup = 0; $lookup < 10000; $lookup++) {
        $handlers->for($noop);
    }
}, 10000);
$measure('execute a no-op operation', static function () use ($runtime, $noop, $system): void {
    for ($run = 0; $run < 1000; $run++) {
        $runtime->execute($noop, $system);
    }
}, 1000);
$measure('execute with validation', static function () use ($runtime, $archive, $system): void {
    for ($run = 0; $run < 1000; $run++) {
        $runtime->execute($archive, $system);
    }
}, 1000);

gc_collect_cycles();
$before = memory_get_usage();

for ($iteration = 0; $iteration < 1000; $iteration++) {
    $runtime->execute($noop, $system);
}

gc_collect_cycles();
printf("Memory growth over 1000 executions: %.1f KB; peak %.1f MB\n", (memory_get_usage() - $before) / 1024, memory_get_peak_usage(true) / 1048576);

$app->make(ExecutionPipeline::class)->middleware(RecordingMiddleware::class);
$app['config']->set('fixtures.calls', []);
$measure('execute with validation and middleware', static function () use ($runtime, $archive, $system, $app): void {
    for ($run = 0; $run < 1000; $run++) {
        $runtime->execute($archive, $system);
    }

    $app['config']->set('fixtures.calls', []);
}, 1000);
