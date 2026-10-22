<?php

declare(strict_types=1);

namespace Gcego\Runtime;

use Gcego\Blueprints\Contracts\BlueprintInterface;
use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Components\Exceptions\InvalidComponentException;
use Gcego\Registry\Identity\DefinitionIdentity;
use Gcego\Registry\Index\DefinitionDescriptor;
use Gcego\Registry\Kinds\BlueprintKind;
use Gcego\Registry\Registry;
use Gcego\Runtime\Contracts\RenderAuthorizer;
use Gcego\Runtime\Exceptions\RenderException;
use Gcego\Runtime\Rendering\ComponentRenderer;
use Gcego\Runtime\Rendering\RenderContext;
use Gcego\Runtime\Rendering\RendererRegistry;
use Gcego\Runtime\Rendering\RenderMode;
use Gcego\Runtime\Rendering\RenderResult;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Foundation\Application;

/**
 * The runtime entry point (a stateless container singleton).
 *
 *     app(Runtime::class)->render($blueprint);              // trusted server code: no authorization
 *     app(Runtime::class)->render('blueprint:subjects');    // resolved through gcego/registry
 *     app(Runtime::class)->renderAuthorized('subjects');    // public entry points: RenderAuthorizer first
 *
 * Lifecycle: resolve -> (authorize) -> validate -> context -> renderer -> lazy Blade output.
 */
final readonly class Runtime
{
    public function __construct(
        private ComponentRenderer $renderer,
        private RendererRegistry $renderers,
        private Registry $registry,
        private RenderAuthorizer $authorizer,
        private RuntimeConfig $config,
        private Application $app,
    ) {}

    /**
     * @param  string|null  $identity  The registry identity of a supplied component, for stable DOM ids and markers.
     * @param  array<string, mixed>  $metadata
     * @param  array<string, array<string, mixed>>  $state
     */
    public function render(
        ComponentInterface|string $target,
        RenderMode $mode = RenderMode::Static,
        ?string $identity = null,
        ?string $panel = null,
        ?string $theme = null,
        int $headingLevel = 1,
        array $metadata = [],
        array $state = [],
    ): RenderResult {
        [$component, $identity] = is_string($target)
            ? [$this->resolve($target), $this->descriptor($target)->key()]
            : [$target, $identity];

        return $this->renderComponent($component, $identity, $mode, $panel, $theme, $headingLevel, $metadata, $state);
    }

    /**
     * Resolve, authorize and render a registered definition (for public entry points).
     *
     * @param  array<string, mixed>  $metadata
     * @param  array<string, array<string, mixed>>  $state
     *
     * @throws AuthorizationException
     */
    public function renderAuthorized(
        string $identifier,
        RenderMode $mode = RenderMode::Static,
        ?string $panel = null,
        ?string $theme = null,
        int $headingLevel = 1,
        array $metadata = [],
        array $state = [],
    ): RenderResult {
        $component = $this->authorizedComponent($identifier);

        return $this->renderComponent($component, $this->descriptor($identifier)->key(), $mode, $panel, $theme, $headingLevel, $metadata, $state);
    }

    /**
     * The registered, authorized blueprint behind an identifier (used by the Livewire host).
     *
     * Authorization runs on the descriptor, before any definition code is loaded.
     *
     * @throws AuthorizationException|RenderException
     */
    public function hostedBlueprint(string $identifier): BlueprintInterface
    {
        $descriptor = $this->descriptor($identifier);

        if ($descriptor->kind() !== BlueprintKind::Id) {
            throw RenderException::notBlueprint($descriptor->key(), $descriptor->kind());
        }

        $this->authorizer->authorize($descriptor);
        $blueprint = $this->registry->get($descriptor->key(), BlueprintInterface::class);

        return $this->validated($blueprint, $descriptor->key());
    }

    /**
     * The canonical identity for an identifier ("subjects" -> "blueprint:subjects").
     */
    public function identityOf(string $identifier): string
    {
        return $this->descriptor($identifier)->key();
    }

    public function canRender(ComponentInterface $component): bool
    {
        return $this->renderers->has($component);
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @param  array<string, array<string, mixed>>  $state
     */
    private function renderComponent(ComponentInterface $component, ?string $identity, RenderMode $mode, ?string $panel, ?string $theme, int $headingLevel, array $metadata, array $state): RenderResult
    {
        $component = $this->validated($component, $identity ?? $component->type());

        $context = RenderContext::root(
            $component,
            identity: $identity,
            mode: $mode,
            locale: $this->app->getLocale(),
            panel: $panel,
            theme: $theme,
            headingLevel: $headingLevel,
            metadata: $metadata,
            state: $state,
        );

        return $this->renderer->render($component, $context);
    }

    /**
     * @template T of ComponentInterface
     *
     * @param  T  $component
     * @return T
     */
    private function validated(ComponentInterface $component, string $identity): ComponentInterface
    {
        if (! $this->config->validate) {
            return $component;
        }

        try {
            return $component->assertValid();
        } catch (InvalidComponentException $exception) {
            throw RenderException::invalidDefinition($identity, $exception);
        }
    }

    private function authorizedComponent(string $identifier): ComponentInterface
    {
        $descriptor = $this->descriptor($identifier);
        $this->authorizer->authorize($descriptor);

        return $this->resolve($identifier);
    }

    private function resolve(string $identifier): ComponentInterface
    {
        $descriptor = $this->descriptor($identifier);
        $definition = $this->registry->get($descriptor->key());

        if (! $definition instanceof ComponentInterface) {
            throw RenderException::notRenderable($descriptor->key(), $definition::class);
        }

        return $definition;
    }

    /**
     * Only registry identities are accepted: never class names or paths.
     */
    private function descriptor(string $identifier): DefinitionDescriptor
    {
        $valid = DefinitionIdentity::isQualified($identifier)
            ? preg_match('/^[a-z0-9:._-]+$/', $identifier) === 1
            : preg_match(DefinitionIdentity::NamePattern, $identifier) === 1;

        if (! $valid) {
            throw RenderException::invalidIdentifier($identifier);
        }

        return $this->registry->descriptor($identifier);
    }
}
