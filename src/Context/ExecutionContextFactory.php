<?php

declare(strict_types=1);

namespace Gcego\Runtime\Context;

use Gcego\Runtime\Contracts\PanelResolver;
use Gcego\Runtime\Contracts\TenantResolver;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;

/**
 * Builds execution contexts from trusted sources only.
 *
 *  - user:   the authentication guard (never an id from input);
 *  - tenant: the TenantResolver, which verifies membership on the server;
 *  - panel:  the PanelResolver, from routing configuration;
 *  - locale: the application locale.
 *
 * A stateless singleton: every call reads the current request and user afresh.
 */
final readonly class ExecutionContextFactory
{
    public function __construct(
        private Application $app,
        private AuthFactory $auth,
        private TenantResolver $tenants,
        private PanelResolver $panels,
    ) {}

    /**
     * The context of the current request (a guest context when nobody is signed in).
     */
    public function current(): ExecutionContext
    {
        $request = $this->app->bound('request') ? $this->app->make('request') : null;

        return $request instanceof Request
            ? $this->fromRequest($request)
            : ExecutionContext::guest(locale: $this->app->getLocale());
    }

    public function fromRequest(Request $request): ExecutionContext
    {
        $user = $this->auth->guard()->user();
        $tenant = $this->tenants->resolve($user, $request);
        $panel = $this->panels->resolve($request);
        $metadata = RequestMetadata::fromRequest($request, $this->app->runningInConsole());
        $locale = $this->app->getLocale();

        return $user === null
            ? ExecutionContext::guest($tenant, $panel, $locale, $metadata)
            : ExecutionContext::forUser($user, $tenant, $panel, $locale, $metadata);
    }

    public function forUser(Authenticatable $user, ?TenantReference $tenant = null, ?string $panel = null): ExecutionContext
    {
        return ExecutionContext::forUser($user, $tenant, $panel, $this->app->getLocale());
    }

    public function system(?TenantReference $tenant = null, ?string $panel = null): ExecutionContext
    {
        return ExecutionContext::system($tenant, $panel, $this->app->getLocale());
    }
}
