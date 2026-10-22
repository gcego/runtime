<?php

declare(strict_types=1);

namespace Gcego\Runtime\Tests\Fixtures;

/**
 * A plain registered definition ("note:welcome"), standing in for the
 * definitions of feature packages that operations target.
 */
final readonly class Note
{
    public function __construct(public string $name, public string $text = '') {}
}
