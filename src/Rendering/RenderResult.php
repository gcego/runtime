<?php

declare(strict_types=1);

namespace Gcego\Runtime\Rendering;

use Closure;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\View\View;
use Stringable;

/**
 * Rendered (or deferred) output of a component.
 *
 * Escaping policy: output comes from Blade views (which escape `{{ }}`),
 * from text() (escaped here), or from trustedHtml(), which is the only way to
 * emit raw markup and must never receive user input. Results are Htmlable,
 * so `{{ $result }}` in Blade prints them without double escaping.
 * Production is lazy: a view renders when the result is printed.
 */
final readonly class RenderResult implements Htmlable, Stringable
{
    /**
     * @param  Closure(): string  $producer
     */
    private function __construct(private Closure $producer) {}

    public static function view(View $view): self
    {
        return new self(static fn (): string => $view->render());
    }

    /**
     * Plain text, escaped.
     */
    public static function text(string $text): self
    {
        return new self(static fn (): string => e($text));
    }

    /**
     * Raw HTML from a trusted source (your own template code, never user input).
     */
    public static function trustedHtml(string $html): self
    {
        return new self(static fn (): string => $html);
    }

    public static function empty(): self
    {
        return new self(static fn (): string => '');
    }

    /**
     * @internal Used by the runtime to wrap results with error boundaries.
     *
     * @param  Closure(): string  $producer
     */
    public static function deferred(Closure $producer): self
    {
        return new self($producer);
    }

    public function toHtml(): string
    {
        return ($this->producer)();
    }

    public function __toString(): string
    {
        return $this->toHtml();
    }
}
