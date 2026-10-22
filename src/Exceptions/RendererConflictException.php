<?php

declare(strict_types=1);

namespace Gcego\Runtime\Exceptions;

use InvalidArgumentException;

/**
 * Thrown when a second renderer is registered for a target without override: true,
 * or when a registration is invalid.
 */
class RendererConflictException extends InvalidArgumentException implements RuntimeException
{
    public static function conflict(string $target, string $existing, string $new): self
    {
        return new self(sprintf(
            '%s already renders %s; %s cannot replace it. Pass override: true to replace a renderer deliberately.',
            $existing,
            $target,
            $new,
        ));
    }

    public static function invalidTarget(string $target): self
    {
        return new self(sprintf('Renderer target %s must be an existing class or interface implementing Gcego\Components\Contracts\ComponentInterface.', $target));
    }

    public static function invalidRenderer(string $renderer): self
    {
        return new self(sprintf('%s must be an existing class implementing Gcego\Runtime\Contracts\RendererInterface.', $renderer));
    }
}
