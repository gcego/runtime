<?php

declare(strict_types=1);

namespace Gcego\Runtime\State;

use Gcego\Components\Trees\ComponentPath;
use Gcego\Runtime\Exceptions\InvalidStateException;

/**
 * The state bucket of one component occurrence.
 *
 * The key is the occurrence path with "." replaced by ":" ("subjects.filters"
 * becomes "subjects:filters"), so it is a single Livewire property segment.
 * Path segments never contain ":" or ".", so keys cannot collide, and they
 * are stable for an unchanged definition.
 */
final readonly class StatePath
{
    public const string Property = 'state';

    public const string FieldPattern = '/^[A-Za-z][A-Za-z0-9_]*$/';

    private function __construct(public string $key) {}

    public static function for(ComponentPath $path): self
    {
        return new self(str_replace('.', ':', $path->toString()));
    }

    /**
     * The Livewire property path for a field: "state.subjects:filters.query".
     */
    public function property(string $field): string
    {
        if (preg_match(self::FieldPattern, $field) !== 1) {
            throw InvalidStateException::rejected(self::Property.'.'.$this->key.'.'.$field, 'field names must be identifiers.');
        }

        return self::Property.'.'.$this->key.'.'.$field;
    }
}
