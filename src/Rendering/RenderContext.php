<?php

declare(strict_types=1);

namespace Gcego\Runtime\Rendering;

use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Components\Traversal\ComponentWalker;
use Gcego\Components\Trees\TreeNode;
use Gcego\Runtime\Exceptions\InvalidRenderContextException;
use Gcego\Runtime\State\StatePath;

/**
 * Immutable, per-render information about one component occurrence.
 *
 * Created for each render and never stored in singletons. It carries only
 * rendering data: no request, user, session or connection objects. State is
 * the Livewire host's (untrusted, already validated) state map.
 */
final readonly class RenderContext
{
    private const string IdentifierPattern = '/^[a-z][a-z0-9]*(?:[._-][a-z0-9]+)*$/';

    /**
     * @param  array<string, string|int|float|bool|null>  $metadata
     * @param  array<string, array<string, mixed>>  $state
     * @param  array<int, true>  $ancestors  Object ids of the components above this one.
     */
    private function __construct(
        public TreeNode $node,
        public ?string $identity,
        public RenderMode $mode,
        public string $locale,
        public ?string $panel,
        public ?string $theme,
        public int $headingLevel,
        public array $metadata,
        public array $state,
        private array $ancestors,
    ) {}

    /**
     * @param  array<string, mixed>  $metadata  Scalar values only.
     * @param  array<string, array<string, mixed>>  $state
     *
     * @throws InvalidRenderContextException
     */
    public static function root(
        ComponentInterface $component,
        ?string $identity = null,
        RenderMode $mode = RenderMode::Static,
        string $locale = 'en',
        ?string $panel = null,
        ?string $theme = null,
        int $headingLevel = 1,
        array $metadata = [],
        array $state = [],
    ): self {
        if ($headingLevel < 1 || $headingLevel > 6) {
            throw InvalidRenderContextException::invalid('headingLevel', 'must be between 1 and 6.');
        }

        foreach (['panel' => $panel, 'theme' => $theme] as $field => $value) {
            if ($value !== null && preg_match(self::IdentifierPattern, $value) !== 1) {
                throw InvalidRenderContextException::invalid($field, 'must be a lowercase identifier such as "admin".');
            }
        }

        if (preg_match('/^[A-Za-z]{2,3}(?:[-_][A-Za-z0-9]{2,8})*$/', $locale) !== 1) {
            throw InvalidRenderContextException::invalid('locale', 'must be a locale such as "en" or "fr_CM".');
        }

        $approved = [];

        foreach ($metadata as $key => $value) {
            if (! is_string($key) || ! ($value === null || is_scalar($value))) {
                throw InvalidRenderContextException::invalid('metadata', 'keys must be strings and values scalars or null.');
            }

            $approved[$key] = $value;
        }

        return new self(app(ComponentWalker::class)->rootNode($component), $identity, $mode, $locale, $panel, $theme, $headingLevel, $approved, $state, []);
    }

    /**
     * Derive the context of a child occurrence.
     */
    public function child(TreeNode $child, ?int $headingLevel = null): self
    {
        return new self(
            $child,
            $this->identity,
            $this->mode,
            $this->locale,
            $this->panel,
            $this->theme,
            $headingLevel === null ? $this->headingLevel : max(1, min(6, $headingLevel)),
            $this->metadata,
            $this->state,
            $this->ancestors + [spl_object_id($this->node->component) => true],
        );
    }

    public function component(): ComponentInterface
    {
        return $this->node->component;
    }

    public function path(): string
    {
        return $this->node->path->toString();
    }

    public function depth(): int
    {
        return $this->node->depth;
    }

    public function isLivewire(): bool
    {
        return $this->mode === RenderMode::Livewire;
    }

    public function nextHeadingLevel(): int
    {
        return min(6, $this->headingLevel + 1);
    }

    public function hasAncestor(ComponentInterface $component): bool
    {
        return isset($this->ancestors[spl_object_id($component)]);
    }

    /**
     * A stable, unique HTML id for this occurrence, optionally suffixed ("…-title").
     */
    public function domId(string $suffix = ''): string
    {
        $base = ($this->identity ?? 'component').'|'.$this->path();
        $slug = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($base)), '-');
        $id = 'gcego-'.substr($slug, 0, 60).'-'.substr(hash('sha256', $base), 0, 8);

        return $suffix === '' ? $id : $id.'-'.trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($suffix)), '-');
    }

    public function statePath(): StatePath
    {
        return StatePath::for($this->node->path);
    }

    /**
     * This occurrence's state (empty outside Livewire or for stateless components).
     *
     * @return array<string, mixed>
     */
    public function state(): array
    {
        return $this->state[$this->statePath()->key] ?? [];
    }

    /**
     * The wire:model target for one of this occurrence's state fields.
     *
     * @throws InvalidRenderContextException outside a Livewire host.
     */
    public function wireModel(string $field): string
    {
        if (! $this->isLivewire()) {
            throw InvalidRenderContextException::notLivewire($this->path());
        }

        return $this->statePath()->property($field);
    }
}
