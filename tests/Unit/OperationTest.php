<?php

declare(strict_types=1);

use Gcego\Runtime\Exceptions\ErrorCategory;
use Gcego\Runtime\Exceptions\InvalidOperationException;
use Gcego\Runtime\Execution\Operation;
use Illuminate\Http\UploadedFile;

it('describes operations of each built-in category', function (): void {
    expect(Operation::make('report')->on('note:welcome')->toArray())->toBe(['type' => 'report', 'name' => 'default', 'target' => 'note:welcome'])
        ->and(Operation::action('delete-subject')->on('blueprint:subjects')->key())->toBe('action:delete-subject@blueprint:subjects')
        ->and(Operation::query('students')->key())->toBe('query:students')
        ->and(Operation::submit('subject-form')->type)->toBe('form')
        ->and(Operation::make('workflow', 'approve-leave')->key())->toBe('workflow:approve-leave');
});

it('is immutable', function (): void {
    $operation = Operation::action('archive');

    $targeted = $operation->on('subjects')->with(['record' => 5]);

    expect($operation->target)->toBeNull()
        ->and($operation->input)->toBe([])
        ->and($targeted->target)->toBe('subjects')
        ->and($targeted->input('record'))->toBe(5);
});

it('rejects malformed types and names', function (string $type, string $name): void {
    expect(fn () => Operation::make($type, $name))->toThrow(InvalidOperationException::class);
})->with([
    'uppercase type' => ['Action', 'x'],
    'class name type' => ['App\\Actions', 'x'],
    'empty name' => ['action', ''],
    'spaces' => ['action', 'delete subject'],
    'overlong name' => ['action', str_repeat('a', 129)],
]);

it('accepts only registry identities as targets', function (string $target): void {
    expect(fn () => Operation::action('archive')->on($target))->toThrow(InvalidOperationException::class, 'is not a definition identifier');
})->with([
    'class name' => ['App\\Models\\User'],
    'path' => ['../../etc/passwd'],
    'uppercase' => ['Blueprint:Subjects'],
]);

it('accepts data-shaped input and uploaded files', function (): void {
    $file = UploadedFile::fake()->create('report.pdf');

    $operation = Operation::submit('import')->with(['name' => 'Ada', 'tags' => ['a', 'b'], 'nested' => ['deep' => [1, 2.5, true, null]], 'file' => $file]);

    expect($operation->input('nested.deep.1'))->toBe(2.5)
        ->and($operation->input('file'))->toBe($file);
});

it('rejects input that is not plain data', function (array $input, string $message): void {
    expect(fn () => Operation::action('archive')->with($input))->toThrow(InvalidOperationException::class, $message);
})->with([
    'object' => [['user' => new stdClass], '"user" must be a scalar, null, an array or an uploaded file, not stdClass'],
    'closure' => [['callback' => fn () => null], 'not Closure'],
    'list at the top level' => [[1, 2], 'top-level keys must be field names'],
    'too deep' => [['a' => array_reduce(range(1, 20), fn (array $carry): array => ['x' => $carry], [])], 'must not be nested deeper'],
]);

it('leaves the untrusted input out of its log representation', function (): void {
    expect(Operation::action('login')->with(['password' => 'secret'])->toArray())->not->toHaveKey('input');
});

it('reports malformed operations as client errors', function (): void {
    try {
        Operation::make('BAD');
    } catch (InvalidOperationException $exception) {
        expect($exception->category)->toBe(ErrorCategory::InvalidOperation)
            ->and($exception->status())->toBe(400);
    }
});
