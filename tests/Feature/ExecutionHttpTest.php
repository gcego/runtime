<?php

declare(strict_types=1);

use Gcego\Runtime\Contracts\ActionHandler;
use Gcego\Runtime\Execution\ExecutionRequest;
use Gcego\Runtime\Execution\HandlerRegistry;
use Gcego\Runtime\Execution\Operation;
use Gcego\Runtime\Facades\Runtime;
use Gcego\Runtime\Tests\Fixtures\ArchiveHandler;
use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

beforeEach(function (): void {
    app(HandlerRegistry::class)->register(Operation::Action, ArchiveHandler::class, name: 'archive');
    app(HandlerRegistry::class)->register(Operation::Action, new class implements ActionHandler
    {
        public function handle(ExecutionRequest $request): mixed
        {
            throw new RuntimeException('SQLSTATE[42S02]: table secrets missing');
        }
    }, name: 'explode');
    Gate::define('gcego-runtime.execute', fn (?object $user, ExecutionRequest $request): bool => $user?->getAuthIdentifier() === 7);
    Route::post('/actions/{action}', fn (string $action) => Runtime::execute(Operation::action($action)->with(request()->all())));
});

it('returns the result as JSON', function (): void {
    $this->actingAs(new GenericUser(['id' => 7]));

    $this->postJson('/actions/archive', ['record' => 5])->assertOk()->assertJson(['status' => 'succeeded', 'data' => ['archived' => 5]]);
});

it('returns 401 for guests', function (): void {
    $this->postJson('/actions/archive', ['record' => 5])->assertUnauthorized();
});

it('returns 403 for users the authorizer denies', function (): void {
    $this->actingAs(new GenericUser(['id' => 8]));

    $this->postJson('/actions/archive', ['record' => 5])->assertForbidden()->assertJson(['message' => 'This action is unauthorized.']);
});

it('returns 422 with field errors for invalid input', function (): void {
    $this->actingAs(new GenericUser(['id' => 7]));

    $this->postJson('/actions/archive', ['record' => 'abc'])->assertUnprocessable()->assertJsonValidationErrors(['record' => 'The record field must be an integer.']);
});

it('returns 500 without internal details when a handler fails', function (): void {
    config()->set('app.debug', false);
    $this->actingAs(new GenericUser(['id' => 7]));

    $this->postJson('/actions/explode')->assertInternalServerError()->assertDontSee('SQLSTATE')->assertDontSee('secrets');
});

it('returns 501 for operations without a handler', function (): void {
    config()->set('app.debug', false);
    $this->actingAs(new GenericUser(['id' => 7]));

    $this->postJson('/actions/unknown')->assertStatus(501)->assertJson(['message' => 'This operation is not supported.']);
});
