<?php

declare(strict_types=1);

namespace Gcego\Runtime\Tests\Fixtures;

use Gcego\Runtime\Context\TenantReference;
use Gcego\Runtime\Contracts\TenantResolver;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;

/**
 * Resolves the tenant from a server-side membership map, by host name.
 * Request input ("tenant" parameters or headers) is deliberately ignored.
 */
final readonly class HostTenantResolver implements TenantResolver
{
    /**
     * @var array<string|int, array<string, int>> user id => host => tenant id
     */
    private const array Memberships = [7 => ['north.test' => 1], 8 => ['south.test' => 2]];

    public function resolve(?Authenticatable $user, Request $request): ?TenantReference
    {
        $id = $user?->getAuthIdentifier();
        $tenant = is_int($id) || is_string($id) ? (self::Memberships[$id][$request->getHost()] ?? null) : null;

        return $tenant === null ? null : new TenantReference($tenant, 'school');
    }
}
