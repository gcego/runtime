<?php

declare(strict_types=1);

namespace Gcego\Runtime\Context;

use Gcego\Runtime\Contracts\PanelResolver;
use Illuminate\Http\Request;

/**
 * Default: requests belong to no panel.
 */
final readonly class NullPanelResolver implements PanelResolver
{
    public function resolve(Request $request): ?string
    {
        return null;
    }
}
