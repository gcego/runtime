<?php

declare(strict_types=1);

use Gcego\Runtime\Contracts\ActionHandler;
use Gcego\Runtime\Contracts\FormSubmissionHandler;
use Gcego\Runtime\Contracts\OperationHandler;
use Gcego\Runtime\Contracts\QueryHandler;
use Gcego\Runtime\Exceptions\HandlerRegistrationException;
use Gcego\Runtime\Exceptions\OperationNotSupportedException;
use Gcego\Runtime\Execution\ExecutionRequest;
use Gcego\Runtime\Execution\HandlerRegistry;
use Gcego\Runtime\Execution\Operation;
use Gcego\Runtime\Tests\Fixtures\ArchiveHandler;
use Gcego\Runtime\Tests\Fixtures\CountingActionHandler;
use Gcego\Runtime\Tests\Fixtures\GenericHandler;

it('ships typed categories without handlers', function (): void {
    $registry = app(HandlerRegistry::class);

    expect($registry->categories())->toBe([
        'action' => ActionHandler::class,
        'query' => QueryHandler::class,
        'form' => FormSubmissionHandler::class,
    ])
        ->and($registry->bindings())->toBe([])
        ->and($registry->has(Operation::query('students')))->toBeFalse();
});

it('requires handlers to implement their category contract', function (): void {
    $registry = new HandlerRegistry(app());

    expect(fn () => $registry->register(Operation::Query, ArchiveHandler::class))
        ->toThrow(HandlerRegistrationException::class, ArchiveHandler::class.' must be an existing class implementing '.QueryHandler::class)
        ->and(fn () => $registry->register(Operation::Action, stdClass::class))->toThrow(HandlerRegistrationException::class)
        ->and(fn () => $registry->register(Operation::Action, GenericHandler::class))->toThrow(HandlerRegistrationException::class);
});

it('refuses handlers for unknown categories until the category is declared', function (): void {
    $registry = new HandlerRegistry(app());

    expect(fn () => $registry->register('workflow', GenericHandler::class))->toThrow(HandlerRegistrationException::class, '"workflow" is not a registered operation category');

    $registry->category('workflow');
    $registry->register('workflow', GenericHandler::class);

    expect($registry->for(Operation::make('workflow', 'approve')))->toBeInstanceOf(GenericHandler::class);
});

it('rejects invalid or conflicting categories', function (): void {
    $registry = new HandlerRegistry(app());

    expect(fn () => $registry->category('Work Flow'))->toThrow(HandlerRegistrationException::class, 'Invalid operation type')
        ->and(fn () => $registry->category('workflow', stdClass::class))->toThrow(HandlerRegistrationException::class, 'must be an interface extending')
        ->and(fn () => $registry->category(Operation::Action, QueryHandler::class))->toThrow(HandlerRegistrationException::class, 'already requires '.ActionHandler::class);
});

it('prefers a handler registered for the operation name over the category handler', function (): void {
    $registry = new HandlerRegistry(app());
    $registry->register(Operation::Action, CountingActionHandler::class);
    $registry->register(Operation::Action, ArchiveHandler::class, name: 'archive');

    expect($registry->for(Operation::action('archive')))->toBeInstanceOf(ArchiveHandler::class)
        ->and($registry->for(Operation::action('publish')))->toBeInstanceOf(CountingActionHandler::class);
});

it('rejects conflicting registrations unless overriding explicitly', function (): void {
    $registry = new HandlerRegistry(app());
    $registry->register(Operation::Action, ArchiveHandler::class, name: 'archive');
    $registry->register(Operation::Action, ArchiveHandler::class, name: 'archive');

    expect(fn () => $registry->register(Operation::Action, CountingActionHandler::class, name: 'archive'))
        ->toThrow(HandlerRegistrationException::class, ArchiveHandler::class.' already handles action:archive');

    $registry->register(Operation::Action, CountingActionHandler::class, name: 'archive', override: true);

    expect($registry->for(Operation::action('archive')))->toBeInstanceOf(CountingActionHandler::class)
        ->and($registry->overrides())->toBe([['key' => 'action:archive', 'previous' => ArchiveHandler::class, 'current' => CountingActionHandler::class]]);
});

it('builds each handler once through the container', function (): void {
    CountingActionHandler::$instances = 0;
    $registry = new HandlerRegistry(app());
    $registry->register(Operation::Action, CountingActionHandler::class);

    for ($index = 0; $index < 50; $index++) {
        $registry->for(Operation::action('a'.$index));
    }

    expect(CountingActionHandler::$instances)->toBe(1);
});

it('reports operations without a handler as unsupported', function (): void {
    $registry = new HandlerRegistry(app());

    expect(fn () => $registry->for(Operation::query('students')))->toThrow(OperationNotSupportedException::class, 'No handler is registered for "query" operations named "students"')
        ->and(fn () => $registry->for(Operation::make('workflow')))->toThrow(OperationNotSupportedException::class, '"workflow" is not a registered operation category');
});

it('accepts handler instances and lists bindings', function (): void {
    $registry = new HandlerRegistry(app());
    $handler = new class implements QueryHandler
    {
        public function handle(ExecutionRequest $request): mixed
        {
            return [];
        }
    };

    $registry->register(Operation::Query, $handler, name: 'students');

    expect($registry->for(Operation::query('students')))->toBe($handler)
        ->and($registry->bindings()[0]->toArray())->toBe(['type' => 'query', 'name' => 'students', 'handler' => $handler::class])
        ->and($handler)->toBeInstanceOf(OperationHandler::class);
});
