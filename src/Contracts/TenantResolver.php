<?php

declare(strict_types=1);

namespace Gcego\Runtime\Contracts;

use Gcego\Runtime\Context\TenantReference;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;

/**
 * Determines the verified tenant of a request.
 *
 * Implementations must verify membership on the server (for example, a
 * session value set after a membership check, or a domain mapping); they
 * must never trust a tenant id from request input. Return null when the
 * request has no tenant. gcego/security or the application binds this.
 */
interface TenantResolver
{
    public function resolve(?Authenticatable $user, Request $request): ?TenantReference;
}
