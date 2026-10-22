<?php

declare(strict_types=1);

namespace Gcego\Runtime\Styling;

use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Runtime\Contracts\StylingResolver;
use Gcego\Runtime\Rendering\RenderContext;

/**
 * The default until gcego/chroma is installed: no classes, so built-in
 * markup is plain semantic HTML.
 */
final readonly class NullStylingResolver implements StylingResolver
{
    public function classes(ComponentInterface $component, string $slot, RenderContext $context): string
    {
        return '';
    }
}
