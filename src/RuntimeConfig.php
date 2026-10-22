<?php

declare(strict_types=1);

namespace Gcego\Runtime;

use Gcego\Runtime\Exceptions\InvalidConfigurationException;

/**
 * A validated, typed view of config('gcego-runtime').
 */
final readonly class RuntimeConfig
{
    /**
     * @param  list<string>  $middleware  ExecutionMiddleware class names, checked when the package boots.
     */
    public function __construct(
        public string $ability = 'gcego-runtime.execute',
        public array $middleware = [],
        public int $maxNesting = 8,
    ) {}

    /**
     * @param  array<mixed>  $config
     *
     * @throws InvalidConfigurationException
     */
    public static function fromArray(array $config): self
    {
        $execution = is_array($config['execution'] ?? null) ? $config['execution'] : [];

        $ability = $execution['ability'] ?? 'gcego-runtime.execute';
        $middleware = $execution['middleware'] ?? [];
        $maxNesting = $execution['max_depth'] ?? 8;

        if (! is_string($ability) || $ability === '') {
            throw InvalidConfigurationException::invalid('gcego-runtime.execution.ability', 'must be a non-empty Gate ability name.');
        }

        if (! is_array($middleware) || ! array_is_list($middleware) || array_filter($middleware, static fn (mixed $class): bool => ! is_string($class) || $class === '') !== []) {
            throw InvalidConfigurationException::invalid('gcego-runtime.execution.middleware', 'must be a list of ExecutionMiddleware class names.');
        }

        if (! is_int($maxNesting) || $maxNesting < 1 || $maxNesting > 64) {
            throw InvalidConfigurationException::invalid('gcego-runtime.execution.max_depth', 'must be an integer between 1 and 64.');
        }

        /** @var list<string> $middleware */
        return new self($ability, $middleware, $maxNesting);
    }
}
