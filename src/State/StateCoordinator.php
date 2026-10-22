<?php

declare(strict_types=1);

namespace Gcego\Runtime\State;

use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Components\Traversal\ComponentWalker;
use Gcego\Runtime\Contracts\StatefulComponent;
use Gcego\Runtime\Exceptions\InvalidStateException;

/**
 * GCEGO-specific structure on top of Livewire state: one bucket per
 * StatefulComponent occurrence, keyed by StatePath.
 *
 * Livewire still owns hydration and updates. This class only derives the
 * allowed shape from the trusted, server-side definition and checks client
 * updates against it; it never resolves classes from client input.
 */
final readonly class StateCoordinator
{
    public function __construct(
        private ?ComponentWalker $walker = null,
    ) {}

    /**
     * @return array<string, array<string, mixed>>
     *
     * @throws InvalidStateException when a component declares invalid initial state.
     */
    public function initialState(ComponentInterface $root): array
    {
        $state = [];

        foreach ($this->occurrences($root) as $key => $component) {
            $state[$key] = $this->validInitialState($component);
        }

        return $state;
    }

    /**
     * Keep only known buckets and fields; fill anything missing from the initial state.
     *
     * @param  array<mixed>  $state
     * @return array<string, array<string, mixed>>
     */
    public function sanitize(ComponentInterface $root, array $state): array
    {
        $clean = [];

        foreach ($this->occurrences($root) as $key => $component) {
            $bucket = is_array($state[$key] ?? null) ? $state[$key] : [];
            $clean[$key] = [];

            foreach ($this->validInitialState($component) as $field => $initial) {
                $clean[$key][$field] = array_key_exists($field, $bucket) ? $bucket[$field] : $initial;
            }
        }

        return $clean;
    }

    /**
     * Validate a Livewire update such as ("state.subjects:search.query", "maths").
     *
     * @return array{key: string, field: string, value: mixed}
     *
     * @throws InvalidStateException
     */
    public function accept(ComponentInterface $root, string $property, mixed $value): array
    {
        $segments = explode('.', $property);

        if (count($segments) !== 3 || $segments[0] !== StatePath::Property) {
            throw InvalidStateException::unknownPath($property);
        }

        [, $key, $field] = $segments;
        $component = $this->occurrences($root)[$key] ?? null;

        if ($component === null || ! array_key_exists($field, $this->validInitialState($component))) {
            throw InvalidStateException::unknownPath($property);
        }

        $accepted = $component->acceptState($field, $value);

        if (! self::isStateValue($accepted)) {
            throw InvalidStateException::rejected($property, 'the component accepted a value that is not a scalar, list of scalars or null.');
        }

        return ['key' => $key, 'field' => $field, 'value' => $accepted];
    }

    /**
     * Stateful occurrences by state key, in document order.
     *
     * @return array<string, StatefulComponent>
     */
    public function occurrences(ComponentInterface $root): array
    {
        $occurrences = [];

        foreach (($this->walker ?? app(ComponentWalker::class))->preOrder($root) as $node) {
            if ($node->component instanceof StatefulComponent) {
                $occurrences[StatePath::for($node->path)->key] = $node->component;
            }
        }

        return $occurrences;
    }

    /**
     * @return array<string, mixed>
     */
    private function validInitialState(StatefulComponent $component): array
    {
        $state = $component->initialState();

        foreach ($state as $field => $value) {
            if (! is_string($field) || preg_match(StatePath::FieldPattern, $field) !== 1) {
                throw InvalidStateException::invalidInitialState($component::class, 'field names must be identifiers.');
            }

            if (! self::isStateValue($value)) {
                throw InvalidStateException::invalidInitialState($component::class, sprintf('field "%s" must be null, a scalar or a list of scalars.', $field));
            }
        }

        return $state;
    }

    private static function isStateValue(mixed $value): bool
    {
        if ($value === null || is_scalar($value)) {
            return true;
        }

        if (! is_array($value) || ! array_is_list($value)) {
            return false;
        }

        foreach ($value as $item) {
            if (! is_scalar($item)) {
                return false;
            }
        }

        return true;
    }
}
