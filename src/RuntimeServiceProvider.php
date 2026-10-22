<?php

declare(strict_types=1);

namespace Gcego\Runtime;

use Gcego\Blueprints\Contracts\BlueprintInterface;
use Gcego\Components\Component;
use Gcego\Components\ComponentServiceProvider;
use Gcego\Core\Contracts\PackageInterface;
use Gcego\Core\Packages\PackageDescriptor;
use Gcego\Runtime\Contracts\RenderAuthorizer;
use Gcego\Runtime\Contracts\StylingResolver;
use Gcego\Runtime\Livewire\BlueprintHost;
use Gcego\Runtime\Rendering\BlueprintRenderer;
use Gcego\Runtime\Rendering\ComponentRenderer;
use Gcego\Runtime\Rendering\ContainerRenderer;
use Gcego\Runtime\Rendering\RendererRegistry;
use Gcego\Runtime\Security\GateRenderAuthorizer;
use Gcego\Runtime\State\StateCoordinator;
use Gcego\Runtime\Styling\NullStylingResolver;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

/**
 * Laravel integration for gcego/runtime; also the "gcego/runtime" GCEGO package.
 *
 * register() declares bindings and the built-in renderers; boot() registers
 * the view namespace, publishing and the Livewire host. Nothing is rendered,
 * discovered or loaded here, and no routes are registered.
 */
final class RuntimeServiceProvider extends ServiceProvider implements PackageInterface
{
    public const string Views = 'gcego-runtime';

    public function register(): void
    {
        $this->app->register(ComponentServiceProvider::class);

        $this->mergeConfigFrom(self::path('config/gcego-runtime.php'), 'gcego-runtime');

        $this->app->singleton(RuntimeConfig::class, static function (Application $app): RuntimeConfig {
            $config = $app->make(Repository::class);
            $runtime = $config->get('gcego-runtime', []);

            return RuntimeConfig::fromArray(is_array($runtime) ? $runtime : [], (bool) $config->get('app.debug', false));
        });

        $this->app->singleton(RendererRegistry::class, static function (Application $app): RendererRegistry {
            $registry = new RendererRegistry($app);
            $registry->register(BlueprintInterface::class, BlueprintRenderer::class);
            $registry->register(Component::class, ContainerRenderer::class, inherit: false);

            return $registry;
        });

        $this->app->singleton(StylingResolver::class, NullStylingResolver::class);
        $this->app->singleton(RenderAuthorizer::class, static fn (Application $app): RenderAuthorizer => new GateRenderAuthorizer(
            $app->make(Gate::class),
            $app->make(RuntimeConfig::class)->ability,
        ));
        $this->app->singleton(ComponentRenderer::class);
        $this->app->singleton(StateCoordinator::class);
        $this->app->singleton(Runtime::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(self::path('resources/views'), self::Views);

        if ($this->app->runningInConsole()) {
            $this->publishes([self::path('config/gcego-runtime.php') => $this->app->configPath('gcego-runtime.php')], 'gcego-runtime-config');
            $this->publishes([self::path('resources/views') => $this->app->resourcePath('views/vendor/'.self::Views)], 'gcego-runtime-views');
        }

        $config = $this->app->make(RuntimeConfig::class);

        if ($config->livewireEnabled) {
            Livewire::component($config->livewireAlias, BlueprintHost::class);
        }
    }

    public function descriptor(): PackageDescriptor
    {
        return new PackageDescriptor(
            'gcego/runtime',
            requires: ['gcego/core', 'gcego/registry'],
            capabilities: ['runtime', 'runtime.livewire'],
            description: 'Renderer registry, Blade rendering and Livewire hosting.',
        );
    }

    private static function path(string $relative): string
    {
        return dirname(__DIR__).'/'.$relative;
    }
}
