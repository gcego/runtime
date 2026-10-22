<?php

declare(strict_types=1);

namespace Gcego\Runtime\Blade;

use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Runtime\Contracts\RendererInterface;
use Gcego\Runtime\Contracts\StylingResolver;
use Gcego\Runtime\Exceptions\RenderException;
use Gcego\Runtime\Rendering\ComponentRenderer;
use Gcego\Runtime\Rendering\RenderContext;
use Gcego\Runtime\Rendering\RenderResult;
use Gcego\Runtime\Styling\StyleSlots;
use Illuminate\Contracts\View\Factory;

/**
 * Base class for renderers backed by a Blade view.
 *
 * The view receives:
 *  - $component  the component (read with $component->get(...); always print with {{ }})
 *  - $context    the RenderContext
 *  - $children   Children, rendered where you print them: {{ $children }}
 *  - $styles     StyleSlots: {{ $styles('root') }} or <div{{ $styles->attribute('root') }}>
 *  - whatever data() returns
 *
 * Subclasses are resolved through the container; a subclass with its own
 * constructor must pass these dependencies to parent::__construct().
 */
abstract class BladeRenderer implements RendererInterface
{
    public function __construct(
        protected readonly Factory $views,
        protected readonly ComponentRenderer $renderer,
        protected readonly StylingResolver $styling,
    ) {}

    /**
     * The view name, e.g. "my-package::renderers.chart". Applications may
     * override package views through resources/views/vendor/<namespace>.
     */
    abstract protected function view(): string;

    /**
     * Extra view data.
     *
     * @return array<string, mixed>
     */
    protected function data(ComponentInterface $component, RenderContext $context): array
    {
        return [];
    }

    /**
     * Heading level for this component's children (default: unchanged).
     */
    protected function childHeadingLevel(ComponentInterface $component, RenderContext $context): int
    {
        return $context->headingLevel;
    }

    public function render(ComponentInterface $component, RenderContext $context): RenderResult
    {
        $view = $this->view();

        if (! $this->views->exists($view)) {
            throw RenderException::missingView($view, static::class);
        }

        return RenderResult::view($this->views->make($view, [
            ...$this->data($component, $context),
            'component' => $component,
            'context' => $context,
            'children' => $this->renderer->children($context, $this->childHeadingLevel($component, $context)),
            'styles' => new StyleSlots($this->styling, $component, $context),
        ]));
    }
}
