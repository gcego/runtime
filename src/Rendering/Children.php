<?php

declare(strict_types=1);

namespace Gcego\Runtime\Rendering;

use ArrayIterator;
use Countable;
use Gcego\Components\Traversal\ComponentWalker;
use Gcego\Components\Trees\TreeNode;
use Illuminate\Contracts\Support\Htmlable;
use IteratorAggregate;
use Traversable;

/**
 * A component's renderable children, rendered lazily where the parent places them.
 *
 * `{{ $children }}` renders all of them in order; `@foreach ($children as $child)`
 * renders one at a time (each a RenderResult). Configuration nodes are never
 * children, so they are never rendered.
 *
 * @implements IteratorAggregate<int, RenderResult>
 */
final readonly class Children implements Countable, Htmlable, IteratorAggregate
{
    /**
     * @var list<TreeNode>
     */
    private array $nodes;

    public function __construct(
        private RenderContext $context,
        private ComponentRenderer $renderer,
        private ?int $headingLevel = null,
        ?ComponentWalker $walker = null,
    ) {
        $this->nodes = ($walker ?? app(ComponentWalker::class))->childNodes($context->node);
    }

    /**
     * @return list<TreeNode>
     */
    public function nodes(): array
    {
        return $this->nodes;
    }

    public function render(int $index): RenderResult
    {
        $node = $this->nodes[$index] ?? null;

        return $node === null
            ? RenderResult::empty()
            : $this->renderer->render($node->component, $this->context->child($node, $this->headingLevel));
    }

    public function isEmpty(): bool
    {
        return $this->nodes === [];
    }

    public function count(): int
    {
        return count($this->nodes);
    }

    public function toHtml(): string
    {
        $html = '';

        foreach (array_keys($this->nodes) as $index) {
            $html .= $this->render($index)->toHtml();
        }

        return $html;
    }

    /**
     * @return Traversable<int, RenderResult>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator(array_map(fn (int $index): RenderResult => $this->render($index), array_keys($this->nodes)));
    }
}
