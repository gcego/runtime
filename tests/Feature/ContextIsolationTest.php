<?php

declare(strict_types=1);

use Gcego\Runtime\Context\ExecutionContext;
use Gcego\Runtime\Context\ExecutionContextFactory;
use Gcego\Runtime\Context\TenantReference;
use Gcego\Runtime\Contracts\ActionHandler;
use Gcego\Runtime\Contracts\PanelResolver;
use Gcego\Runtime\Contracts\TenantResolver;
use Gcego\Runtime\Exceptions\ContextViolationException;
use Gcego\Runtime\Exceptions\ErrorCategory;
use Gcego\Runtime\Execution\ExecutionKernel;
use Gcego\Runtime\Execution\ExecutionRequest;
use Gcego\Runtime\Execution\ExecutionScope;
use Gcego\Runtime\Execution\HandlerRegistry;
use Gcego\Runtime\Execution\Operation;
use Gcego\Runtime\Facades\Runtime;
use Gcego\Runtime\Tests\Fixtures\HostTenantResolver;
use Illuminate\Auth\GenericUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

beforeEach(function (): void {
    app()->singleton(TenantResolver::class, HostTenantResolver::class);
    Gate::define('gcego-runtime.execute', fn (?object $user, ExecutionRequest $request): bool => true);
    app(HandlerRegistry::class)->register(Operation::Action, new class implements ActionHandler
    {
        public function handle(ExecutionRequest $request): mixed
        {
            return $request->context->toArray();
        }
    }, name: 'whoami');
    Route::post('/whoami', fn () => Runtime::execute(Operation::action('whoami')->with(request()->all()))->data());
});

it('takes the user from the guard and the tenant from the resolver, never from request input', function (): void {
    $this->actingAs(new GenericUser(['id' => 7]));

    $response = $this->postJson('http://north.test/whoami?tenant=2', ['user' => 8, 'tenant' => 2, 'panel' => 'root', 'system' => true], ['X-Tenant' => '2']);

    $response->assertOk()->assertJson(['system' => false, 'user' => 7, 'tenant' => 'school:1', 'panel' => null]);
});

it('gives no tenant to users without a membership', function (): void {
    $this->actingAs(new GenericUser(['id' => 7]));

    $this->postJson('http://south.test/whoami')->assertOk()->assertJson(['user' => 7, 'tenant' => null]);
});

it('runs requests without a signed-in user as guests', function (): void {
    $this->postJson('http://north.test/whoami', ['user' => 7])->assertOk()->assertJson(['system' => false, 'user' => null, 'tenant' => null]);
});

it('takes the panel from the panel resolver', function (): void {
    app()->instance(PanelResolver::class, new class implements PanelResolver
    {
        public function resolve(Request $request): ?string
        {
            return $request->getHost() === 'admin.test' ? 'admin' : null;
        }
    });
    app()->forgetInstance(ExecutionContextFactory::class);

    $this->postJson('http://admin.test/whoami', ['panel' => 'portal'])->assertOk()->assertJson(['panel' => 'admin']);
});

it('records request metadata without trusting unsafe correlation ids', function (): void {
    $safe = $this->postJson('/whoami', [], ['X-Request-Id' => 'abc-123'])->json('request');
    $unsafe = $this->postJson('/whoami', [], ['X-Request-Id' => '<script>'])->json('request');

    expect($safe)->toMatchArray(['channel' => 'http', 'method' => 'POST', 'path' => '/whoami', 'correlation_id' => 'abc-123'])
        ->and($unsafe['correlation_id'])->toBeNull()
        ->and($safe['id'])->not->toBe($unsafe['id']);
});

it('does not carry one request\'s user or tenant into the next', function (): void {
    $this->actingAs(new GenericUser(['id' => 7]));
    $first = $this->postJson('http://north.test/whoami')->json();

    app('auth')->forgetGuards();
    $second = $this->postJson('http://north.test/whoami')->json();

    expect($first)->toMatchArray(['user' => 7, 'tenant' => 'school:1'])
        ->and($second)->toMatchArray(['user' => null, 'tenant' => null])
        ->and(app(ExecutionScope::class)->isActive())->toBeFalse();
});

it('lets nested operations inherit the parent context', function (): void {
    app(HandlerRegistry::class)->register(Operation::Action, new class implements ActionHandler
    {
        public function handle(ExecutionRequest $request): mixed
        {
            return Runtime::execute(Operation::action('whoami'))->data();
        }
    }, name: 'outer');

    $parent = ExecutionContext::forUser(new GenericUser(['id' => 7]), new TenantReference(1, 'school'));

    expect(Runtime::execute(Operation::action('outer'), $parent)->data())->toMatchArray(['user' => 7, 'tenant' => 'school:1']);
});

it('refuses nested operations that switch user or tenant', function (ExecutionContext $nested): void {
    app(HandlerRegistry::class)->register(Operation::Action, new class($nested) implements ActionHandler
    {
        public function __construct(private ExecutionContext $nested) {}

        public function handle(ExecutionRequest $request): mixed
        {
            return Runtime::execute(Operation::action('whoami'), $this->nested)->data();
        }
    }, name: 'escalate');

    $result = Runtime::attempt(Operation::action('escalate'), ExecutionContext::forUser(new GenericUser(['id' => 7]), new TenantReference(1)));

    expect($result->error)->toBeInstanceOf(ContextViolationException::class)
        ->and($result->category())->toBe(ErrorCategory::ContextViolation);
})->with([
    'another user' => fn () => ExecutionContext::forUser(new GenericUser(['id' => 8]), new TenantReference(1)),
    'another tenant' => fn () => ExecutionContext::forUser(new GenericUser(['id' => 7]), new TenantReference(2)),
    'system privileges' => fn () => ExecutionContext::system(new TenantReference(1)),
]);

it('lets system operations act for other subjects', function (): void {
    $member = ExecutionContext::forUser(new GenericUser(['id' => 8]), new TenantReference(2));
    app(HandlerRegistry::class)->register(Operation::Action, new class($member) implements ActionHandler
    {
        public function __construct(private ExecutionContext $member) {}

        public function handle(ExecutionRequest $request): mixed
        {
            return Runtime::execute(Operation::action('whoami'), $this->member)->data();
        }
    }, name: 'impersonate');

    expect(Runtime::execute(Operation::action('impersonate'), ExecutionContext::system())->data())->toMatchArray(['user' => 8, 'tenant' => '2']);
});

it('limits nesting depth', function (): void {
    $this->rebuildApplication(['gcego-runtime.execution.max_depth' => 3]);
    Gate::define('gcego-runtime.execute', fn (?object $user, ExecutionRequest $request): bool => true);
    app(HandlerRegistry::class)->register(Operation::Action, new class implements ActionHandler
    {
        public function handle(ExecutionRequest $request): mixed
        {
            return Runtime::execute(Operation::action('recurse'))->data();
        }
    }, name: 'recurse');

    $result = Runtime::attempt(Operation::action('recurse'), ExecutionContext::system());

    expect($result->error)->toBeInstanceOf(ContextViolationException::class)
        ->and($result->error?->getMessage())->toContain('maximum nesting depth of 3');
});

it('clears the scope after failures', function (): void {
    Runtime::attempt(Operation::query('students'), ExecutionContext::system());

    expect(app(ExecutionScope::class)->isActive())->toBeFalse()
        ->and(app(ExecutionKernel::class)->currentContext())->toBeNull();
});
