<?php

declare(strict_types=1);

namespace Gcego\Runtime\Contracts;

use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Runtime\Rendering\RenderContext;
use Gcego\Runtime\Rendering\RenderResult;

/**
 * Turns one component occurrence into output.
 *
 * Renderers are shared container singletons and must be stateless: keep
 * everything request-specific in the RenderContext. Components stay
 * declarative; they never render themselves.
 */
interface RendererInterface
{
    public function render(ComponentInterface $component, RenderContext $context): RenderResult;
}
