<?php

declare(strict_types=1);

namespace Gcego\Runtime\Context;

use Gcego\Runtime\Exceptions\InvalidOperationException;
use Gcego\Runtime\Support\Identifiers;

/**
 * The verified tenant an execution runs for, by key and optional type.
 *
 * Only a TenantResolver (or trusted server code) creates one; never build it
 * from request input such as a "tenant" query parameter or header.
 */
final readonly class TenantReference
{
    public function __construct(
        public string|int $key,
        public ?string $type = null,
    ) {
        if ($key === '' || (is_string($key) && strlen($key) > 191)) {
            throw InvalidOperationException::invalid('tenant', 'the key must be a non-empty string of at most 191 characters or an integer.');
        }

        if ($type !== null && ! Identifiers::isName($type)) {
            throw InvalidOperationException::invalid('tenant', 'the type must be a lowercase identifier such as "school".');
        }
    }

    public function equals(?self $other): bool
    {
        return $other !== null && (string) $this->key === (string) $other->key && $this->type === $other->type;
    }

    public function toString(): string
    {
        return ($this->type === null ? '' : $this->type.':').$this->key;
    }
}
