<?php

declare(strict_types=1);

namespace Gcego\Runtime\Facades;

use Gcego\Runtime\Context\ExecutionContext;
use Gcego\Runtime\Context\ExecutionContextFactory;
use Gcego\Runtime\Contracts\RuntimeInterface;
use Gcego\Runtime\Execution\ExecutionResult;
use Gcego\Runtime\Execution\Operation;
use Gcego\Runtime\Runtime as RuntimeService;
use Illuminate\Support\Facades\Facade;

/**
 * Static access to the execution kernel, auto-aliased as \Runtime.
 *
 *     Runtime::execute(Operation::action('archive')->on('blueprint:subjects')->with(['record' => 5]));
 *     Runtime::attempt($operation, Runtime::contexts()->system());
 *
 * Resolves RuntimeInterface from the container, so it serves whichever
 * implementation the application binds and can be replaced in tests with
 * Runtime::swap() or Runtime::shouldReceive(), although the Runtime class is final.
 *
 * @method static ExecutionResult execute(Operation $operation, ?ExecutionContext $context = null)
 * @method static ExecutionResult attempt(Operation $operation, ?ExecutionContext $context = null)
 * @method static ExecutionContextFactory contexts()
 *
 * @see RuntimeService
 * @see RuntimeInterface
 */
final class Runtime extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return RuntimeInterface::class;
    }

    /**
     * Mock the contract: the runtime implementation is final.
     */
    protected static function getMockableClass(): string
    {
        return RuntimeInterface::class;
    }
}
