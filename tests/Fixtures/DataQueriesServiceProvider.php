<?php

declare(strict_types=1);

namespace Gcego\Runtime\Tests\Fixtures;

use Gcego\Core\Extensions\ExtensionManager;
use Illuminate\Support\ServiceProvider;

final class DataQueriesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->make(ExtensionManager::class)->register(new DataQueriesExtension);
    }
}
