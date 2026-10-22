<?php

declare(strict_types=1);

namespace Gcego\Runtime\Rendering;

use Gcego\Runtime\Contracts\RendererInterface;

/**
 * One registration: which components (target class or interface) a renderer handles.
 */
final readonly class RendererBinding
{
    /**
     * @param  class-string  $target
     * @param  class-string<RendererInterface>|RendererInterface  $renderer
     * @param  bool  $inherit  Whether subclasses / implementers of $target match too.
     */
    public function __construct(
        public string $target,
        public string|RendererInterface $renderer,
        public bool $inherit,
    ) {}

    /**
     * @return class-string<RendererInterface>
     */
    public function rendererClass(): string
    {
        return is_string($this->renderer) ? $this->renderer : $this->renderer::class;
    }

    public function sameAs(self $other): bool
    {
        return $this->target === $other->target && $this->inherit === $other->inherit && $this->rendererClass() === $other->rendererClass();
    }

    /**
     * @return array{target: class-string, renderer: class-string<RendererInterface>, inherit: bool}
     */
    public function toArray(): array
    {
        return ['target' => $this->target, 'renderer' => $this->rendererClass(), 'inherit' => $this->inherit];
    }
}
