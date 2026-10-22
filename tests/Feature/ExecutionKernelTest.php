<?php

declare(strict_types=1);

use Gcego\Registry\Registry;
use Gcego\Runtime\Context\ExecutionContext;
use Gcego\Runtime\Contracts\ActionHandler;
use Gcego\Runtime\Contracts\AuthorizesOperation;
use Gcego\Runtime\Contracts\ExecutionAuthorizer;
use Gcego\Runtime\Contracts\ExecutionMiddleware;
use Gcego\Runtime\Contracts\ObservesLifecycle;
use Gcego\Runtime\Exceptions\ErrorCategory;
use Gcego\Runtime\Exceptions\ExecutionDeniedException;
use Gcego\Runtime\Exceptions\ExecutionException;
use Gcego\Runtime\Exceptions\HandlerFailedException;
use Gcego\Runtime\Exceptions\HandlerRegistrationException;
use Gcego\Runtime\Exceptions\OperationNotSupportedException;
use Gcego\Runtime\Exceptions\OperationValidationException;
use Gcego\Runtime\Exceptions\TargetNotFoundException;
use Gcego\Runtime\Execution\Events\OperationExecuted;
use Gcego\Runtime\Execution\Events\OperationExecuting;
use Gcego\Runtime\Execution\Events\OperationFailed;
use Gcego\Runtime\Execution\ExecutionPipeline;
use Gcego\Runtime\Execution\ExecutionRequest;
use Gcego\Runtime\Execution\ExecutionResult;
use Gcego\Runtime\Execution\HandlerRegistry;
use Gcego\Runtime\Execution\Operation;
use Gcego\Runtime\Execution\ResultStatus;
use Gcego\Runtime\Facades\Runtime;
use Gcego\Runtime\Tests\Fixtures\ArchiveHandler;
use Gcego\Runtime\Tests\Fixtures\Note;
use Gcego\Runtime\Tests\Fixtures\RecordingMiddleware;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\GenericUser;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;

beforeEach(function (): void {
    config()->set('fixtures.calls', []);
    app(HandlerRegistry::class)->register(Operation::Action, ArchiveHandler::class, name: 'archive');
    Gate::define('gcego-runtime.execute', fn (?object $user, ExecutionRequest $request): bool => $user !== null);
});

function member(int $id = 7): ExecutionContext
{
    return ExecutionContext::forUser(new GenericUser(['id' => $id]));
}

function calls(): array
{
    return config('fixtures.calls');
}

it('executes an authorized operation and returns a standardized result', function (): void {
    $result = Runtime::execute(Operation::action('archive')->with(['record' => 5]), member());

    expect($result->status)->toBe(ResultStatus::Succeeded)
        ->and($result->data())->toBe(['archived' => 5, 'input' => ['record' => 5], 'user' => 7])
        ->and($result->trace?->stageNames())->toBe(['resolve', 'authorize', 'handler', 'validate', 'execute'])
        ->and($result->trace?->handler())->toBe(ArchiveHandler::class)
        ->and($result->toArray())->toMatchArray(['status' => 'succeeded', 'data' => ['archived' => 5, 'input' => ['record' => 5], 'user' => 7]]);
});

it('fails closed with 403 when the application never defined the ability', function (): void {
    $this->rebuildApplication(['gcego-runtime.execution.ability' => 'never-defined']);
    app(HandlerRegistry::class)->register(Operation::Action, ArchiveHandler::class, name: 'archive');

    expect(fn () => Runtime::execute(Operation::action('archive')->with(['record' => 5]), member()))
        ->toThrow(ExecutionDeniedException::class, 'Operation "action:archive" was denied.');
});

it('denies guests with 401 and users with 403', function (): void {
    Gate::define('gcego-runtime.execute', fn (?object $user, ExecutionRequest $request): bool => false);

    $guest = Runtime::attempt(Operation::action('archive')->with(['record' => 5]), ExecutionContext::guest());
    $user = Runtime::attempt(Operation::action('archive')->with(['record' => 5]), member());

    expect($guest->category())->toBe(ErrorCategory::Unauthenticated)
        ->and($guest->error?->status())->toBe(401)
        ->and($user->category())->toBe(ErrorCategory::Forbidden)
        ->and($user->error?->status())->toBe(403);
});

it('authorizes as the context user, not whoever is signed in', function (): void {
    Gate::define('gcego-runtime.execute', fn (?object $user, ExecutionRequest $request): bool => $user?->getAuthIdentifier() === 1);
    $this->actingAs(new GenericUser(['id' => 1]));

    expect(Runtime::attempt(Operation::action('archive')->with(['record' => 5]), member(2))->category())->toBe(ErrorCategory::Forbidden);
});

it('lets trusted system contexts execute without a Gate ability', function (): void {
    Gate::define('gcego-runtime.execute', fn (?object $user, ExecutionRequest $request): bool => false);

    expect(Runtime::execute(Operation::action('archive')->with(['record' => 5]), ExecutionContext::system())->data()['archived'])->toBe(5);
});

it('authorizes on the descriptor before the target definition is loaded', function (): void {
    app(Registry::class)->register(new Note('subjects'));
    $seen = null;
    Gate::define('gcego-runtime.execute', function (?object $user, ExecutionRequest $request) use (&$seen): bool {
        $seen = [$request->target?->identity(), $request->target?->isLoaded()];

        return false;
    });

    Runtime::attempt(Operation::action('archive')->on('subjects'), member());

    expect($seen)->toBe(['note:subjects', false]);
});

it('denies the operation when authorization itself fails, and reports the cause', function (): void {
    $reported = [];
    app(ExceptionHandler::class)->reportable(function (Throwable $exception) use (&$reported): void {
        $reported[] = $exception::class;
    });
    app()->instance(ExecutionAuthorizer::class, new class implements ExecutionAuthorizer
    {
        public function authorize(ExecutionRequest $request): void
        {
            throw new RuntimeException('policy store offline');
        }
    });

    $result = Runtime::attempt(Operation::action('archive')->with(['record' => 5]), member());

    expect($result->category())->toBe(ErrorCategory::Forbidden)
        ->and($result->error?->getPrevious())->toBeInstanceOf(RuntimeException::class)
        ->and($reported)->toBe([RuntimeException::class]);
});

it('requires the handler authorization to pass as well', function (bool|Throwable $decision): void {
    app(HandlerRegistry::class)->register(Operation::Action, new class($decision) implements ActionHandler, AuthorizesOperation
    {
        public function __construct(private bool|Throwable $decision) {}

        public function authorize(ExecutionRequest $request): bool
        {
            return $this->decision instanceof Throwable ? throw $this->decision : $this->decision;
        }

        public function handle(ExecutionRequest $request): string
        {
            return 'published';
        }
    }, name: 'publish');

    expect(Runtime::attempt(Operation::action('publish'), member())->category())->toBe(ErrorCategory::Forbidden);
})->with([
    'denied' => [false],
    'policy exception' => [new AuthorizationException],
    'unexpected exception' => [new LogicException('broken policy')],
]);

it('rejects invalid input with 422 and passes only validated fields to the handler', function (): void {
    $failure = Runtime::attempt(Operation::action('archive')->with(['record' => 'abc']), member());
    $success = Runtime::execute(Operation::action('archive')->with(['record' => 5, 'is_admin' => true]), member());

    expect($failure->error)->toBeInstanceOf(OperationValidationException::class)
        ->and($failure->error?->status())->toBe(422)
        ->and($failure->toArray()['error']['errors'])->toBe(['record' => ['The record field must be an integer.']])
        ->and($success->data()['input'])->toBe(['record' => 5]);
});

it('validates only after authorization', function (): void {
    Gate::define('gcego-runtime.execute', fn (?object $user, ExecutionRequest $request): bool => false);

    expect(Runtime::attempt(Operation::action('archive')->with(['record' => 'abc']), member())->category())->toBe(ErrorCategory::Forbidden);
});

it('reports unknown targets as 404 and missing handlers as unsupported', function (): void {
    expect(Runtime::attempt(Operation::action('archive')->on('blueprint:missing'), member())->error)->toBeInstanceOf(TargetNotFoundException::class)
        ->and(Runtime::attempt(Operation::query('students'), member())->error)->toBeInstanceOf(OperationNotSupportedException::class);
});

it('does not reveal which handlers exist to callers it denies', function (): void {
    Gate::define('gcego-runtime.execute', fn (?object $user, ExecutionRequest $request): bool => false);

    expect(Runtime::attempt(Operation::query('students'), member())->category())->toBe(ErrorCategory::Forbidden);
});

it('wraps handler failures, keeping the cause but hiding it from users', function (): void {
    app(HandlerRegistry::class)->register(Operation::Action, new class implements ActionHandler
    {
        public function handle(ExecutionRequest $request): mixed
        {
            throw new RuntimeException('SQLSTATE[42S02]: table secrets missing');
        }
    }, name: 'explode');

    $result = Runtime::attempt(Operation::action('explode'), member());

    expect($result->error)->toBeInstanceOf(HandlerFailedException::class)
        ->and($result->error?->getMessage())->toContain('SQLSTATE[42S02]')
        ->and($result->error?->getPrevious())->toBeInstanceOf(RuntimeException::class)
        ->and(json_encode($result))->not->toContain('SQLSTATE')
        ->and($result->toArray()['error'])->toBe(['category' => 'handler_failed', 'status' => 500, 'message' => 'The operation could not be completed.']);
});

it('throws categorized exceptions from execute() and returns them from attempt()', function (): void {
    expect(fn () => Runtime::execute(Operation::query('students'), member()))->toThrow(OperationNotSupportedException::class);

    $result = Runtime::attempt(Operation::query('students'), member());

    expect($result->failed())->toBeTrue()
        ->and($result->trace?->status())->toBe(ResultStatus::Failed)
        ->and(fn () => $result->throw())->toThrow(OperationNotSupportedException::class);
});

it('reports only server-side failures from attempt()', function (): void {
    $reported = [];
    app(ExceptionHandler::class)->reportable(function (ExecutionException $exception) use (&$reported): void {
        $reported[] = $exception->category->value;
    });

    Runtime::attempt(Operation::action('archive')->with(['record' => 'abc']), member());
    Runtime::attempt(Operation::query('students'), member());

    expect($reported)->toBe(['unsupported']);
});

it('runs lifecycle hooks and events in order', function (): void {
    Event::listen(OperationExecuting::class, fn () => config()->push('fixtures.calls', 'event:executing'));
    Event::listen(OperationExecuted::class, fn () => config()->push('fixtures.calls', 'event:executed'));
    Event::listen(OperationFailed::class, fn (OperationFailed $event) => config()->push('fixtures.calls', 'event:failed:'.$event->exception->category->value));
    app(HandlerRegistry::class)->register(Operation::Action, new class implements ActionHandler, ObservesLifecycle
    {
        public function before(ExecutionRequest $request): void
        {
            config()->push('fixtures.calls', 'hook:before');
        }

        public function handle(ExecutionRequest $request): mixed
        {
            config()->push('fixtures.calls', 'handle');

            return $request->input('fail') === true ? throw new RuntimeException('boom') : 'ok';
        }

        public function succeeded(ExecutionRequest $request, ExecutionResult $result): void
        {
            config()->push('fixtures.calls', 'hook:succeeded');

            throw new RuntimeException('ignored: hooks never change the outcome');
        }

        public function failed(ExecutionRequest $request, ExecutionException $exception): void
        {
            config()->push('fixtures.calls', 'hook:failed');
        }
    }, name: 'observe');

    $success = Runtime::execute(Operation::action('observe'), member());
    Runtime::attempt(Operation::action('observe')->with(['fail' => true]), member());

    expect($success->data())->toBe('ok')
        ->and(calls())->toBe([
            'event:executing', 'hook:before', 'handle', 'hook:succeeded', 'event:executed',
            'event:executing', 'hook:before', 'handle', 'hook:failed', 'event:failed:handler_failed',
        ]);
});

it('runs middleware around the handler, only after authorization and validation', function (): void {
    app(ExecutionPipeline::class)->middleware(RecordingMiddleware::class);

    Runtime::execute(Operation::action('archive')->with(['record' => 5]), member());
    Runtime::attempt(Operation::action('archive')->with(['record' => 'abc']), member());
    Runtime::attempt(Operation::action('archive')->with(['record' => 5]), ExecutionContext::guest());

    expect(calls())->toBe(['middleware:before', 'middleware:after']);
});

it('adds configured middleware when the package boots', function (): void {
    $this->rebuildApplication(['gcego-runtime.execution.middleware' => [RecordingMiddleware::class]]);

    expect(app(ExecutionPipeline::class)->middlewareClasses())->toBe([RecordingMiddleware::class]);
});

it('rejects classes that are not middleware', function (): void {
    expect(fn () => app(ExecutionPipeline::class)->middleware(stdClass::class))
        ->toThrow(HandlerRegistrationException::class, 'stdClass must be an existing class implementing '.ExecutionMiddleware::class);
});

it('refuses to execute until GCEGO Studio has booted', function (): void {
    $this->rebuildApplication(['gcego.enabled' => false]);

    expect(fn () => Runtime::execute(Operation::action('archive'), ExecutionContext::system()))
        ->toThrow(ExecutionException::class, 'GCEGO Studio has not booted');
});
