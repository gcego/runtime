<?php

declare(strict_types=1);

namespace Gcego\Runtime\Contracts;

use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Runtime\Exceptions\InvalidStateException;

/**
 * A component with client-editable state inside a Livewire host.
 *
 * Every occurrence gets its own state bucket keyed by its occurrence path.
 * Only the fields declared by initialState() exist; every client update
 * goes through acceptState() before it is stored.
 */
interface StatefulComponent extends ComponentInterface
{
    /**
     * Field name => initial value. Values must be null, scalars or lists of scalars.
     *
     * @return array<string, scalar|list<scalar>|null>
     */
    public function initialState(): array;

    /**
     * Validate and normalize a client-supplied value for $field.
     *
     * @return scalar|list<scalar>|null
     *
     * @throws InvalidStateException when the value is not acceptable.
     */
    public function acceptState(string $field, mixed $value): mixed;
}
