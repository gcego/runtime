<?php

declare(strict_types=1);

namespace Gcego\Runtime\Rendering;

use Gcego\Blueprints\Contracts\BlueprintInterface;
use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Runtime\Blade\BladeRenderer;

/**
 * Built-in renderer for every Blueprint: a labelled <section> with a heading,
 * optional description, then its parts in declaration order. No role is
 * required; each part is drawn by its own renderer.
 */
final class BlueprintRenderer extends BladeRenderer
{
    protected function view(): string
    {
        return 'gcego-runtime::renderers.blueprint';
    }

    protected function data(ComponentInterface $component, RenderContext $context): array
    {
        $metadata = $component instanceof BlueprintInterface ? $component->metadata() : null;

        return [
            'title' => $metadata->title ?? $component->type(),
            'description' => $metadata?->description,
            'level' => $context->headingLevel,
        ];
    }

    protected function childHeadingLevel(ComponentInterface $component, RenderContext $context): int
    {
        return $context->nextHeadingLevel();
    }
}
