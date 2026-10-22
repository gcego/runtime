<?php

declare(strict_types=1);

namespace Gcego\Runtime;

use Gcego\Runtime\Exceptions\InvalidRenderContextException;

/**
 * A validated, typed view of config('gcego-runtime').
 */
final readonly class RuntimeConfig
{
    public const string AliasPattern = '/^[a-z][a-z0-9]*(?:[.-][a-z0-9]+)*$/';

    public function __construct(
        public bool $strict = true,
        public bool $validate = true,
        public int $maxDepth = 64,
        public bool $livewireEnabled = true,
        public string $livewireAlias = 'gcego-blueprint',
        public string $ability = 'gcego-runtime.render',
        public bool $debug = false,
    ) {}

    /**
     * @param  array<mixed>  $config
     *
     * @throws InvalidRenderContextException
     */
    public static function fromArray(array $config, bool $debug = false): self
    {
        $rendering = is_array($config['rendering'] ?? null) ? $config['rendering'] : [];
        $livewire = is_array($config['livewire'] ?? null) ? $config['livewire'] : [];
        $authorization = is_array($config['authorization'] ?? null) ? $config['authorization'] : [];

        $maxDepth = $rendering['max_depth'] ?? 64;
        $alias = $livewire['alias'] ?? 'gcego-blueprint';
        $ability = $authorization['ability'] ?? 'gcego-runtime.render';

        if (! is_int($maxDepth) || $maxDepth < 1 || $maxDepth > 256) {
            throw InvalidRenderContextException::invalid('gcego-runtime.rendering.max_depth', 'must be an integer between 1 and 256.');
        }

        if (! is_string($alias) || preg_match(self::AliasPattern, $alias) !== 1) {
            throw InvalidRenderContextException::invalid('gcego-runtime.livewire.alias', 'must be a lowercase component name such as "gcego-blueprint".');
        }

        if (! is_string($ability) || $ability === '') {
            throw InvalidRenderContextException::invalid('gcego-runtime.authorization.ability', 'must be a non-empty Gate ability name.');
        }

        return new self(
            strict: self::bool($rendering['strict'] ?? true, 'rendering.strict'),
            validate: self::bool($rendering['validate'] ?? true, 'rendering.validate'),
            maxDepth: $maxDepth,
            livewireEnabled: self::bool($livewire['enabled'] ?? true, 'livewire.enabled'),
            livewireAlias: $alias,
            ability: $ability,
            debug: $debug,
        );
    }

    private static function bool(mixed $value, string $key): bool
    {
        $parsed = is_bool($value) ? $value : filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

        if (! is_bool($parsed)) {
            throw InvalidRenderContextException::invalid('gcego-runtime.'.$key, 'must be a boolean.');
        }

        return $parsed;
    }
}
