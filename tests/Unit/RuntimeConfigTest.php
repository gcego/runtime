<?php

declare(strict_types=1);

use Gcego\Runtime\Exceptions\InvalidConfigurationException;
use Gcego\Runtime\RuntimeConfig;

it('reads the shipped defaults', function (): void {
    $config = RuntimeConfig::fromArray(require dirname(__DIR__, 2).'/config/gcego-runtime.php');

    expect($config->ability)->toBe('gcego-runtime.execute')
        ->and($config->middleware)->toBe([])
        ->and($config->maxNesting)->toBe(8);
});

it('rejects invalid values', function (array $config, string $message): void {
    expect(fn () => RuntimeConfig::fromArray($config))->toThrow(InvalidConfigurationException::class, $message);
})->with([
    [['execution' => ['ability' => 7]], 'execution.ability'],
    [['execution' => ['ability' => '']], 'execution.ability'],
    [['execution' => ['middleware' => 'App\\Middleware']], 'execution.middleware'],
    [['execution' => ['middleware' => ['a' => 'App\\Middleware']]], 'execution.middleware'],
    [['execution' => ['max_depth' => 0]], 'execution.max_depth'],
]);
