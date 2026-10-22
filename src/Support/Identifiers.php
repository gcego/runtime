<?php

declare(strict_types=1);

namespace Gcego\Runtime\Support;

use Gcego\Registry\Identity\DefinitionIdentity;

/**
 * Identifier rules for operations, contexts and registry targets, shared
 * with packages that build on the runtime (such as gcego/rendering).
 */
final class Identifiers
{
    /** Operation types, names, panels and themes: "admin", "delete-subject", "tables.export". */
    public const string NamePattern = '/^[a-z][a-z0-9]*(?:[._-][a-z0-9]+)*$/';

    public const string LocalePattern = '/^[A-Za-z]{2,3}(?:[-_][A-Za-z0-9]{2,8})*$/';

    public static function isName(mixed $value): bool
    {
        return is_string($value) && strlen($value) <= 128 && preg_match(self::NamePattern, $value) === 1;
    }

    /**
     * Only registry identities ("kind:name", "kind:scope.name" or a bare name), never class names or paths.
     */
    public static function isDefinition(string $identifier): bool
    {
        if (strlen($identifier) > 255) {
            return false;
        }

        return DefinitionIdentity::isQualified($identifier)
            ? preg_match('/^[a-z0-9:._-]+$/', $identifier) === 1
            : preg_match(DefinitionIdentity::NamePattern, $identifier) === 1;
    }
}
