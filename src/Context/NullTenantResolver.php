<?php

declare(strict_types=1);

namespace Gcego\Runtime\Context;

use Gcego\Runtime\Contracts\TenantResolver;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;

/**
 * Default: requests have no tenant.
 */
final readonly class NullTenantResolver implements TenantResolver
{
    public function resolve(?Authenticatable $user, Request $request): ?TenantReference
    {
        return null;
    }
}
