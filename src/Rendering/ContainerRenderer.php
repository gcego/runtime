<?php

declare(strict_types=1);

namespace Gcego\Runtime\Rendering;

use Gcego\Components\Component;
use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Runtime\Blade\BladeRenderer;

/**
 * Built-in renderer for the generic Gcego\Components\Component (exact class
 * only): a <div>, or a labelled <section> with a heading when it has a label,
 * containing its children in order.
 */
final class ContainerRenderer extends BladeRenderer
{
    protected function view(): string
    {
        return 'gcego-runtime::renderers.container';
    }

    protected function data(ComponentInterface $component, RenderContext $context): array
    {
        $label = $component->get(Component::Label);
        $description = $component->get(Component::Description);

        return [
            'label' => is_string($label) && $label !== '' ? $label : null,
            'description' => is_string($description) && $description !== '' ? $description : null,
            'level' => $context->headingLevel,
        ];
    }

    protected function childHeadingLevel(ComponentInterface $component, RenderContext $context): int
    {
        $label = $component->get(Component::Label);

        return is_string($label) && $label !== '' ? $context->nextHeadingLevel() : $context->headingLevel;
    }
}
