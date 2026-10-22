<?php

declare(strict_types=1);

use Gcego\Blueprints\Blueprint;
use Gcego\Blueprints\Definitions\Model;
use Gcego\Blueprints\Definitions\Name;
use Gcego\Blueprints\Definitions\Title;
use Gcego\Registry\Registry;
use Gcego\Runtime\Contracts\ActionHandler;
use Gcego\Runtime\Exceptions\InvalidOperationException;
use Gcego\Runtime\Execution\ExecutionRequest;
use Gcego\Runtime\Execution\HandlerRegistry;
use Gcego\Runtime\Execution\Operation;
use Gcego\Runtime\Facades\Runtime;
use Gcego\Runtime\Tests\Fixtures\Note;
use Illuminate\Support\Facades\Gate;

beforeEach(function (): void {
    app(Registry::class)->register(Blueprint::define(Name::make('subjects'), Title::make('Subject Management'), Model::make(stdClass::class)));
    Gate::define('gcego-runtime.execute', fn (?object $user, ExecutionRequest $request): bool => true);
});

it('gives handlers the blueprint an operation targets, resolved through gcego/blueprints', function (): void {
    app(HandlerRegistry::class)->register(Operation::Action, new class implements ActionHandler
    {
        public function handle(ExecutionRequest $request): mixed
        {
            $blueprint = $request->target()->blueprint();

            return [$request->target()->identity(), $request->target()->isBlueprint(), $blueprint->title(), $blueprint->model()];
        }
    });

    $result = Runtime::execute(Operation::action('archive')->on('subjects'));

    expect($result->data())->toBe(['blueprint:subjects', true, 'Subject Management', stdClass::class]);
});

it('rejects a blueprint where a handler expects another definition', function (): void {
    app(HandlerRegistry::class)->register(Operation::Action, new class implements ActionHandler
    {
        public function handle(ExecutionRequest $request): mixed
        {
            return $request->target()->definitionAs(Note::class);
        }
    });

    expect(fn () => Runtime::execute(Operation::action('archive')->on('blueprint:subjects')))
        ->toThrow(InvalidOperationException::class, 'Target "blueprint:subjects" is a Gcego\Blueprints\Blueprint');
});

it('rejects definitions that are not blueprints where a blueprint is expected', function (): void {
    app(Registry::class)->register(new Note('welcome'));
    app(HandlerRegistry::class)->register(Operation::Action, new class implements ActionHandler
    {
        public function handle(ExecutionRequest $request): mixed
        {
            return $request->target()->blueprint();
        }
    });

    expect(fn () => Runtime::execute(Operation::action('archive')->on('note:welcome')))
        ->toThrow(InvalidOperationException::class, 'needs a Gcego\Blueprints\Contracts\BlueprintInterface');
});
