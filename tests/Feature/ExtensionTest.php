<?php

declare(strict_types=1);

use Gcego\Core\Gcego;
use Gcego\Runtime\Context\ExecutionContext;
use Gcego\Runtime\Context\TenantReference;
use Gcego\Runtime\Contracts\ActionHandler;
use Gcego\Runtime\Contracts\FormSubmissionHandler;
use Gcego\Runtime\Contracts\OperationHandler;
use Gcego\Runtime\Contracts\ValidatesOperation;
use Gcego\Runtime\Exceptions\ErrorCategory;
use Gcego\Runtime\Execution\ExecutionPipeline;
use Gcego\Runtime\Execution\ExecutionRequest;
use Gcego\Runtime\Execution\ExecutionResult;
use Gcego\Runtime\Execution\HandlerRegistry;
use Gcego\Runtime\Execution\Operation;
use Gcego\Runtime\Facades\Runtime;
use Gcego\Runtime\Tests\Fixtures\DataQueriesServiceProvider;
use Gcego\Runtime\Tests\Fixtures\RecordingMiddleware;
use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Gate;

beforeEach(function (): void {
    Gate::define('gcego-runtime.execute', fn (?object $user, ExecutionRequest $request): bool => true);
});

it('reports query operations as unsupported until a data adapter is installed', function (): void {
    expect(Runtime::attempt(Operation::query('students'), ExecutionContext::system())->category())->toBe(ErrorCategory::Unsupported);
});

it('activates data adapters through gcego/core extensions', function (): void {
    $this->rebuildApplication(providers: [DataQueriesServiceProvider::class]);
    Gate::define('gcego-runtime.execute', fn (?object $user, ExecutionRequest $request): bool => true);
    $context = ExecutionContext::forUser(new GenericUser(['id' => 7]), new TenantReference(1, 'school'));

    $result = Runtime::execute(Operation::query('students')->with(['search' => 'a']), $context);

    expect(app(Gcego::class)->isExtensionActive('fixtures.data-queries'))->toBeTrue()
        ->and($result->data())->toBe([['id' => 1, 'name' => 'Ada'], ['id' => 2, 'name' => 'Alan']])
        ->and(app(ExecutionPipeline::class)->middlewareClasses())->toBe([RecordingMiddleware::class]);
});

it('scopes adapter results to the trusted tenant', function (): void {
    $this->rebuildApplication(providers: [DataQueriesServiceProvider::class]);
    Gate::define('gcego-runtime.execute', fn (?object $user, ExecutionRequest $request): bool => true);

    $result = Runtime::execute(Operation::query('students')->with(['tenant' => 1]), ExecutionContext::forUser(new GenericUser(['id' => 8]), new TenantReference(2, 'school')));

    expect($result->data())->toBe([['id' => 3, 'name' => 'Grace']]);
});

it('executes actions through an adapter registered for the category', function (): void {
    app(HandlerRegistry::class)->register(Operation::Action, new class implements ActionHandler
    {
        public function handle(ExecutionRequest $request): ExecutionResult
        {
            return ExecutionResult::success(['action' => $request->operation->name, 'target' => $request->target?->identity()], ['adapter' => 'gcego/actions']);
        }
    });

    $result = Runtime::execute(Operation::action('export-subjects'), ExecutionContext::system());

    expect($result->data())->toBe(['action' => 'export-subjects', 'target' => null])
        ->and($result->meta)->toBe(['adapter' => 'gcego/actions']);
});

it('routes form submissions to a forms-owned handler', function (): void {
    app(HandlerRegistry::class)->register(Operation::FormSubmission, new class implements FormSubmissionHandler, ValidatesOperation
    {
        public function rules(ExecutionRequest $request): array
        {
            return ['name' => ['required', 'string']];
        }

        public function handle(ExecutionRequest $request): mixed
        {
            return ['saved' => $request->input()];
        }
    }, name: 'subject-form');

    $saved = Runtime::execute(Operation::submit('subject-form')->with(['name' => 'Physics', 'id' => 99]), ExecutionContext::system());
    $invalid = Runtime::attempt(Operation::submit('subject-form'), ExecutionContext::system());

    expect($saved->data())->toBe(['saved' => ['name' => 'Physics']])
        ->and($invalid->category())->toBe(ErrorCategory::ValidationFailed);
});

it('executes operations of categories other packages declare', function (): void {
    $handlers = app(HandlerRegistry::class);
    $handlers->category('workflow');
    $handlers->register('workflow', new class implements OperationHandler
    {
        public function handle(ExecutionRequest $request): string
        {
            return 'approved '.$request->input('request');
        }
    }, name: 'approve-leave');

    expect(Runtime::execute(Operation::make('workflow', 'approve-leave')->with(['request' => 12]), ExecutionContext::system())->data())->toBe('approved 12');
});
