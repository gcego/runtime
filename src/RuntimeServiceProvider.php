<?php

declare(strict_types=1);

namespace Gcego\Runtime;

use Gcego\Core\Bootstrap\BootstrapContext;
use Gcego\Core\Contracts\Bootable;
use Gcego\Core\Contracts\PackageInterface;
use Gcego\Core\Diagnostics\DiagnosticRegistry;
use Gcego\Core\Packages\PackageDescriptor;
use Gcego\Runtime\Context\ExecutionContextFactory;
use Gcego\Runtime\Context\NullPanelResolver;
use Gcego\Runtime\Context\NullTenantResolver;
use Gcego\Runtime\Contracts\ExecutionAuthorizer;
use Gcego\Runtime\Contracts\PanelResolver;
use Gcego\Runtime\Contracts\RuntimeInterface;
use Gcego\Runtime\Contracts\TenantResolver;
use Gcego\Runtime\Diagnostics\ExecutionHealthCheck;
use Gcego\Runtime\Execution\DefinitionResolver;
use Gcego\Runtime\Execution\ExecutionKernel;
use Gcego\Runtime\Execution\ExecutionPipeline;
use Gcego\Runtime\Execution\ExecutionScope;
use Gcego\Runtime\Execution\FailureTranslator;
use Gcego\Runtime\Execution\HandlerRegistry;
use Gcego\Runtime\Security\GateExecutionAuthorizer;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

/**
 * Laravel integration for gcego/runtime; also the "gcego/runtime" GCEGO package.
 *
 * register() declares bindings; boot() registers publishing and the Core
 * diagnostic; bootPackage() (after every GCEGO dependency booted) adds the
 * configured execution middleware. Nothing is executed, discovered or
 * loaded here, and no routes or views are registered.
 */
final class RuntimeServiceProvider extends ServiceProvider implements Bootable, PackageInterface
{
    public function register(): void
    {
        $this->mergeConfigFrom(self::path('config/gcego-runtime.php'), 'gcego-runtime');

        $this->app->singleton(RuntimeConfig::class, static function (Application $app): RuntimeConfig {
            $runtime = $app->make(Repository::class)->get('gcego-runtime', []);

            return RuntimeConfig::fromArray(is_array($runtime) ? $runtime : []);
        });

        $this->app->singleton(HandlerRegistry::class);
        $this->app->singleton(ExecutionAuthorizer::class, static fn (Application $app): ExecutionAuthorizer => new GateExecutionAuthorizer(
            $app->make(Gate::class),
            $app->make(RuntimeConfig::class)->ability,
        ));
        $this->app->singleton(TenantResolver::class, NullTenantResolver::class);
        $this->app->singleton(PanelResolver::class, NullPanelResolver::class);
        $this->app->singleton(ExecutionContextFactory::class);
        $this->app->singleton(DefinitionResolver::class);
        $this->app->singleton(FailureTranslator::class);
        $this->app->singleton(ExecutionPipeline::class);
        $this->app->singleton(ExecutionKernel::class);
        $this->app->scoped(ExecutionScope::class);
        $this->app->singleton(Runtime::class);
        $this->app->alias(Runtime::class, RuntimeInterface::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([self::path('config/gcego-runtime.php') => $this->app->configPath('gcego-runtime.php')], 'gcego-runtime-config');
        }

        if ($this->app->bound(DiagnosticRegistry::class)) {
            $this->app->make(DiagnosticRegistry::class)->register(new ExecutionHealthCheck($this->app));
        }
    }

    /**
     * Runs once, in GCEGO dependency order. Invalid middleware fails the boot
     * here, instead of the first request.
     */
    public function bootPackage(BootstrapContext $context): void
    {
        $this->app->make(ExecutionPipeline::class)->middleware(...$this->app->make(RuntimeConfig::class)->middleware);
    }

    public function descriptor(): PackageDescriptor
    {
        return new PackageDescriptor(
            'gcego/runtime',
            requires: ['gcego/core', 'gcego/registry', 'gcego/blueprints'],
            capabilities: ['runtime', 'runtime.execution'],
            description: 'Execution kernel: trusted contexts, typed operation handlers, fail-closed authorization.',
        );
    }

    private static function path(string $relative): string
    {
        return dirname(__DIR__).'/'.$relative;
    }
}
