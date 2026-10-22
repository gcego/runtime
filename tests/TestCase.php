<?php

declare(strict_types=1);

namespace Gcego\Runtime\Tests;

use Gcego\Blueprints\BlueprintsServiceProvider;
use Gcego\Core\GcegoServiceProvider;
use Gcego\Registry\Kinds\DefinitionKinds;
use Gcego\Registry\RegistryServiceProvider;
use Gcego\Runtime\RuntimeServiceProvider;
use Gcego\Runtime\Tests\Fixtures\NoteKind;
use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /**
     * @var array<string, mixed>
     */
    private array $overrides = [];

    /**
     * @var list<class-string<ServiceProvider>>
     */
    private array $extraProviders = [];

    protected function setUp(): void
    {
        $this->afterApplicationCreated(fn () => $this->registerFixtureKind());

        parent::setUp();
    }

    /**
     * @param  array<string, mixed>  $config  Full config keys, e.g. ['gcego-runtime.execution.max_depth' => 3].
     * @param  list<class-string<ServiceProvider>>  $providers  Extra providers, registered before GCEGO boots.
     */
    public function rebuildApplication(array $config = [], array $providers = []): Application
    {
        $this->overrides = $config;
        $this->extraProviders = $providers;
        $this->refreshApplication();
        $this->registerFixtureKind();

        /** @var Application */
        return $this->app;
    }

    protected function getPackageProviders($app): array
    {
        return [GcegoServiceProvider::class, RegistryServiceProvider::class, BlueprintsServiceProvider::class, RuntimeServiceProvider::class, ...$this->extraProviders];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('k', 32)));
        $app['config']->set('gcego-registry.discovery.enabled', false);
        $app['config']->set('gcego-registry.cache.enabled', false);
        $app['config']->set('gcego-blueprints.discovery.enabled', false);

        foreach ($this->overrides as $key => $value) {
            $app['config']->set($key, $value);
        }
    }

    private function registerFixtureKind(): void
    {
        $kinds = $this->app?->make(DefinitionKinds::class);

        if ($kinds !== null && ! $kinds->has('note')) {
            $kinds->register(new NoteKind);
        }
    }
}
