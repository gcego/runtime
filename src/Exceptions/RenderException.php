<?php

declare(strict_types=1);

namespace Gcego\Runtime\Exceptions;

use RuntimeException as BaseRuntimeException;
use Throwable;

/**
 * A component could not be rendered. Carries the component type, path and renderer.
 */
class RenderException extends BaseRuntimeException implements RuntimeException
{
    public static function failed(string $type, string $path, string $renderer, Throwable $previous): self
    {
        return new self(sprintf(
            'Rendering component "%s" at "%s" with %s failed: %s',
            $type,
            $path,
            $renderer,
            $previous->getMessage(),
        ), 0, $previous);
    }

    public static function missingView(string $view, string $renderer): self
    {
        return new self(sprintf('View [%s] used by %s does not exist.', $view, $renderer));
    }

    public static function tooDeep(string $path, int $maxDepth): self
    {
        return new self(sprintf('Rendering exceeded the maximum depth of %d at "%s".', $maxDepth, $path));
    }

    public static function cycle(string $path, string $class): self
    {
        return new self(sprintf('Circular rendering detected at "%s": %s contains itself.', $path, $class));
    }

    public static function invalidDefinition(string $identity, Throwable $previous): self
    {
        return new self(sprintf('Definition "%s" is invalid and cannot be rendered: %s', $identity, $previous->getMessage()), 0, $previous);
    }

    public static function notRenderable(string $identity, string $class): self
    {
        return new self(sprintf('Definition "%s" is a %s, which is not a component and cannot be rendered.', $identity, $class));
    }

    public static function notBlueprint(string $identity, string $kind): self
    {
        return new self(sprintf('Definition "%s" is a "%s"; only blueprints can be hosted.', $identity, $kind));
    }

    public static function invalidIdentifier(string $identifier): self
    {
        return new self(sprintf('"%s" is not a definition identifier. Use "kind:name" (for example "blueprint:subjects") or a bare name.', mb_substr($identifier, 0, 100)));
    }
}
