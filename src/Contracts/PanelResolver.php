<?php

declare(strict_types=1);

namespace Gcego\Runtime\Contracts;

use Illuminate\Http\Request;

/**
 * Determines the panel ("admin", "portal") a request belongs to, from
 * server-side routing configuration only. gcego/panels or the application binds this.
 */
interface PanelResolver
{
    public function resolve(Request $request): ?string;
}
