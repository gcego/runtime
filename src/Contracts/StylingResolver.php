<?php

declare(strict_types=1);

namespace Gcego\Runtime\Contracts;

use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Runtime\Rendering\RenderContext;

/**
 * Supplies CSS classes for a component's named slot (for example "root", "title").
 *
 * gcego/chroma will bind a Tailwind CSS v4 implementation. Return only
 * class names that Tailwind can discover statically (written in full in PHP
 * or Blade source, or explicitly safelisted); never build utility names from
 * unchecked runtime input. Values are escaped when printed.
 */
interface StylingResolver
{
    public function classes(ComponentInterface $component, string $slot, RenderContext $context): string;
}
