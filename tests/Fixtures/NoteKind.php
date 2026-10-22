<?php

declare(strict_types=1);

namespace Gcego\Runtime\Tests\Fixtures;

use Gcego\Registry\Contracts\DefinitionKind;

final readonly class NoteKind implements DefinitionKind
{
    public function id(): string
    {
        return 'note';
    }

    public function contract(): string
    {
        return Note::class;
    }

    public function name(object $definition): string
    {
        return $definition instanceof Note ? $definition->name : 'unnamed';
    }

    public function validate(object $definition): array
    {
        return [];
    }

    public function metadata(object $definition): array
    {
        return ['length' => $definition instanceof Note ? strlen($definition->text) : 0];
    }
}
