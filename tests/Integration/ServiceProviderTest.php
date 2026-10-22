<?php

declare(strict_types=1);

use Gcego\Core\Diagnostics\DiagnosticRegistry;
use Gcego\Core\Gcego;
use Gcego\Runtime\Context\ExecutionContextFactory;
use Gcego\Runtime\Context\NullPanelResolver;
use Gcego\Runtime\Context\NullTenantResolver;
use Gcego\Runtime\Contracts\ExecutionAuthorizer;
use Gcego\Runtime\Contracts\PanelResolver;
use Gcego\Runtime\Contracts\RuntimeInterface;
use Gcego\Runtime\Contracts\TenantResolver;
use Gcego\Runtime\Diagnostics\ExecutionHealthCheck;
use Gcego\Runtime\Diagnostics\RuntimeInspector;
use Gcego\Runtime\Execution\ExecutionKernel;
use Gcego\Runtime\Execution\ExecutionPipeline;
use Gcego\Runtime\Execution\ExecutionRequest;
use Gcego\Runtime\Execution\ExecutionResult;
use Gcego\Runtime\Execution\ExecutionScope;
use Gcego\Runtime\Execution\HandlerRegistry;
use Gcego\Runtime\Execution\Operation;
use Gcego\Runtime\Facades\Runtime as RuntimeFacade;
use Gcego\Runtime\Runtime;
use Gcego\Runtime\RuntimeConfig;
use Gcego\Runtime\RuntimeServiceProvider;
use Gcego\Runtime\Security\GateExecutionAuthorizer;
use Gcego\Runtime\Tests\Fixtures\ArchiveHandler;
use Illuminate\Auth\GenericUser;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

it('binds its services as singletons', function (string $abstract, string $concrete): void {
    expect(app($abstract))->toBeInstanceOf($concrete)
        ->and(app($abstract))->toBe(app($abstract));
})->with([
    [Runtime::class, Runtime::class],
    [RuntimeInterface::class, Runtime::class],
    [RuntimeConfig::class, RuntimeConfig::class],
    [HandlerRegistry::class, HandlerRegistry::class],
    [ExecutionPipeline::class, ExecutionPipeline::class],
    [ExecutionKernel::class, ExecutionKernel::class],
    [ExecutionContextFactory::class, ExecutionContextFactory::class],
    [ExecutionAuthorizer::class, GateExecutionAuthorizer::class],
    [TenantResolver::class, NullTenantResolver::class],
    [PanelResolver::class, NullPanelResolver::class],
]);

it('exposes the runtime through a swappable facade', function (): void {
    expect(RuntimeFacade::getFacadeRoot())->toBe(app(Runtime::class));

    RuntimeFacade::shouldReceive('execute')->once()->andReturn(ExecutionResult::success('faked'));

    expect(RuntimeFacade::execute(Operation::action('archive'))->data())->toBe('faked');
});

it('scopes the execution scope to one request or job', function (): void {
    $scope = app(ExecutionScope::class);

    expect(app(ExecutionScope::class))->toBe($scope);

    app()->forgetScopedInstances();

    expect(app(ExecutionScope::class))->not->toBe($scope);
});

it('registers config publishing and no views', function (): void {
    expect(ServiceProvider::pathsToPublish(RuntimeServiceProvider::class, 'gcego-runtime-config'))->toHaveCount(1)
        ->and(ServiceProvider::pathsToPublish(RuntimeServiceProvider::class, 'gcego-runtime-views'))->toBe([])
        ->and(view()->getFinder()->getHints())->not->toHaveKey('gcego-runtime');
});

it('takes part in the gcego/core package lifecycle after its dependencies', function (): void {
    $gcego = app(Gcego::class);

    expect($gcego->bootResult()?->packages)->toBe(['gcego/core', 'gcego/registry', 'gcego/blueprints', 'gcego/runtime'])
        ->and($gcego->package('gcego/runtime')->requires)->toBe(['gcego/core', 'gcego/registry', 'gcego/blueprints'])
        ->and($gcego->package('gcego/runtime')->capabilities)->toBe(['runtime', 'runtime.execution']);
});

it('contributes an execution check to gcego:diagnose', function (): void {
    $results = app(DiagnosticRegistry::class)->run();
    $check = array_values(array_filter($results, fn ($result): bool => $result->check === ExecutionHealthCheck::Id))[0] ?? null;

    expect($check?->isFailure())->toBeFalse()
        ->and($check?->details['authorizer'])->toBe(GateExecutionAuthorizer::class);
});

it('does not read env() at runtime and keeps no static state', function (): void {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 2).'/src', FilesystemIterator::SKIP_DOTS));

    foreach ($iterator as $file) {
        if (! $file instanceof SplFileInfo || $file->getExtension() !== 'php') {
            continue;
        }

        $source = (string) file_get_contents($file->getPathname());
        $calls = array_filter(token_get_all($source), fn (mixed $token): bool => is_array($token) && $token[0] === T_STRING && $token[1] === 'env');

        expect($calls)->toBe([], $file->getPathname())
            ->and($source)->not->toMatch('/(private|protected|public)\s+static\s+(?!function)/');
    }
});

it('keeps no request state in shared services across requests', function (): void {
    app(HandlerRegistry::class)->register(Operation::Action, ArchiveHandler::class, name: 'archive');
    Gate::define('gcego-runtime.execute', fn (?object $user, ExecutionRequest $request): bool => true);
    Route::post('/probe', fn () => RuntimeFacade::execute(Operation::action('archive')->with(request()->all())));

    $this->actingAs(new GenericUser(['id' => 7]))->postJson('/probe', ['record' => 1])->assertOk();
    $this->postJson('/probe', ['record' => 2])->assertOk();

    $stack = [app(Runtime::class), app(ExecutionKernel::class), app(ExecutionPipeline::class), app(HandlerRegistry::class), app(ExecutionContextFactory::class)];
    $seen = [];

    while ($stack !== []) {
        $object = array_pop($stack);

        if (isset($seen[spl_object_id($object)]) || $object instanceof Container) {
            continue;
        }

        $seen[spl_object_id($object)] = true;
        expect($object)->not->toBeInstanceOf(Request::class)
            ->and($object)->not->toBeInstanceOf(ExecutionScope::class)
            ->and($object)->not->toBeInstanceOf(GenericUser::class);

        // Framework services (auth manager, container) own their own request lifecycle.
        foreach ((array) $object as $value) {
            foreach (is_array($value) ? $value : [$value] as $item) {
                if (is_object($item) && (str_starts_with($item::class, 'Gcego\\') || $item instanceof Request || $item instanceof GenericUser || $item instanceof ExecutionScope)) {
                    $stack[] = $item;
                }
            }
        }
    }
});

it('depends only on Core, Registry, Blueprints and Laravel', function (): void {
    $composer = json_decode((string) file_get_contents(dirname(__DIR__, 2).'/composer.json'), true, flags: JSON_THROW_ON_ERROR);
    $gcego = array_values(array_filter(array_keys([...$composer['require'], ...$composer['require-dev']]), fn (string $package): bool => str_starts_with($package, 'gcego/')));
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 2).'/src', FilesystemIterator::SKIP_DOTS));

    sort($gcego);

    expect($gcego)->toBe(['gcego/blueprints', 'gcego/core', 'gcego/registry'])
        ->and(array_keys($composer['require']))->not->toContain('livewire/livewire');

    foreach ($iterator as $file) {
        if ($file instanceof SplFileInfo && $file->getExtension() === 'php') {
            preg_match_all('/^use (Gcego\\\\[A-Za-z]+|Livewire)\\\\/m', (string) file_get_contents($file->getPathname()), $imports);

            expect(array_diff(array_unique($imports[1]), ['Gcego\\Core', 'Gcego\\Registry', 'Gcego\\Blueprints', 'Gcego\\Runtime']))->toBe([], $file->getPathname());
        }
    }
});

it('exposes a diagnostic inspector', function (): void {
    expect(app(RuntimeInspector::class)->inspect()->isHealthy())->toBeTrue();
});
