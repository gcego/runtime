<?php

declare(strict_types=1);

namespace Gcego\Runtime\Livewire;

use Gcego\Blueprints\Contracts\BlueprintInterface;
use Gcego\Runtime\Exceptions\InvalidStateException;
use Gcego\Runtime\Rendering\RenderMode;
use Gcego\Runtime\Runtime;
use Gcego\Runtime\State\StateCoordinator;
use Gcego\Runtime\State\StatePath;
use Illuminate\Contracts\View\View;
use Illuminate\Support\HtmlString;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Hosts a registered blueprint: <livewire:gcego-blueprint blueprint="blueprint:subjects" />
 *
 * Browser-visible state is only the identity (locked) and the state map
 * (untrusted). The blueprint is resolved, authorized and validated on the
 * server on every request; it is never serialized. Every state update must
 * address a declared field of a StatefulComponent occurrence and pass that
 * component's acceptState().
 */
final class BlueprintHost extends Component
{
    #[Locked]
    public string $blueprint = '';

    /**
     * @var array<string, array<string, mixed>>
     */
    public array $state = [];

    private ?BlueprintInterface $definition = null;

    public function mount(string $blueprint): void
    {
        $runtime = app(Runtime::class);
        $this->definition = $runtime->hostedBlueprint($blueprint);
        $this->blueprint = $runtime->identityOf($blueprint);
        $this->state = app(StateCoordinator::class)->initialState($this->definition);
    }

    public function hydrate(): void
    {
        $this->state = app(StateCoordinator::class)->sanitize($this->definition(), $this->state);
    }

    /**
     * @throws InvalidStateException for anything but a declared field.
     */
    public function updating(string $property, mixed $value): void
    {
        if ($property === StatePath::Property || str_starts_with($property, StatePath::Property.'.')) {
            app(StateCoordinator::class)->accept($this->definition(), $property, $value);
        }
    }

    public function updated(string $property, mixed $value): void
    {
        if (str_starts_with($property, StatePath::Property.'.')) {
            $accepted = app(StateCoordinator::class)->accept($this->definition(), $property, $value);
            $this->state[$accepted['key']][$accepted['field']] = $accepted['value'];
        }
    }

    public function render(): View
    {
        // Rendered eagerly so failures surface as runtime exceptions, not Blade ViewExceptions.
        $content = app(Runtime::class)->render($this->definition(), mode: RenderMode::Livewire, identity: $this->blueprint, state: $this->state)->toHtml();

        return view('gcego-runtime::hosts.blueprint', [
            'identity' => $this->blueprint,
            'content' => new HtmlString($content),
        ]);
    }

    private function definition(): BlueprintInterface
    {
        return $this->definition ??= app(Runtime::class)->hostedBlueprint($this->blueprint);
    }
}
