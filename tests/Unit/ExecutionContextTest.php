<?php

declare(strict_types=1);

use Gcego\Runtime\Context\ExecutionContext;
use Gcego\Runtime\Context\TenantReference;
use Gcego\Runtime\Exceptions\InvalidOperationException;
use Illuminate\Auth\GenericUser;

it('describes users, guests and trusted system code', function (): void {
    $user = new GenericUser(['id' => 7]);

    $forUser = ExecutionContext::forUser($user, new TenantReference(3, 'school'), 'admin', 'fr_CM');
    $guest = ExecutionContext::guest();
    $system = ExecutionContext::system();

    expect($forUser->userId())->toBe(7)
        ->and($forUser->tenant?->toString())->toBe('school:3')
        ->and($forUser->panel)->toBe('admin')
        ->and($forUser->isGuest())->toBeFalse()
        ->and($guest->isGuest())->toBeTrue()
        ->and($system->isSystem())->toBeTrue()
        ->and($system->isGuest())->toBeFalse();
});

it('only adds descriptive attributes, never changing user, tenant, panel or privilege', function (): void {
    $context = ExecutionContext::forUser(new GenericUser(['id' => 7]), new TenantReference(3), 'admin');

    $derived = $context->withAttributes(['source' => 'import']);

    expect($derived->attribute('source'))->toBe('import')
        ->and($derived->sameSubjectAs($context))->toBeTrue()
        ->and($derived->panel)->toBe('admin')
        ->and($derived->isSystem())->toBeFalse()
        ->and($context->attributes)->toBe([]);
});

it('compares subjects by user, tenant and privilege', function (ExecutionContext $other, bool $same): void {
    $context = ExecutionContext::forUser(new GenericUser(['id' => 7]), new TenantReference(3));

    expect($context->sameSubjectAs($other))->toBe($same);
})->with([
    'same user and tenant' => fn () => [ExecutionContext::forUser(new GenericUser(['id' => 7]), new TenantReference('3')), true],
    'another user' => fn () => [ExecutionContext::forUser(new GenericUser(['id' => 8]), new TenantReference(3)), false],
    'another tenant' => fn () => [ExecutionContext::forUser(new GenericUser(['id' => 7]), new TenantReference(4)), false],
    'no tenant' => fn () => [ExecutionContext::forUser(new GenericUser(['id' => 7])), false],
    'guest' => fn () => [ExecutionContext::guest(new TenantReference(3)), false],
    'system' => fn () => [ExecutionContext::system(new TenantReference(3)), false],
]);

it('rejects invalid context values', function (Closure $make, string $message): void {
    expect($make)->toThrow(InvalidOperationException::class, $message);
})->with([
    'panel' => [fn () => ExecutionContext::guest(panel: 'Admin Panel'), 'context panel'],
    'locale' => [fn () => ExecutionContext::guest(locale: 'english!'), 'context locale'],
    'attribute value' => [fn () => ExecutionContext::guest(attributes: ['user' => ['id' => 1]]), 'context attributes'],
    'attribute key' => [fn () => ExecutionContext::guest(attributes: ['Bad Key' => 1]), 'context attributes'],
    'tenant key' => [fn () => new TenantReference(''), 'tenant'],
    'tenant type' => [fn () => new TenantReference(1, 'Not Valid'), 'tenant'],
]);

it('summarizes itself without the user object or attributes', function (): void {
    $summary = ExecutionContext::forUser(new GenericUser(['id' => 7, 'password' => 'secret']), attributes: ['note' => 'x'])->toArray();

    expect($summary)->toMatchArray(['system' => false, 'user' => 7, 'tenant' => null, 'panel' => null])
        ->and($summary)->not->toHaveKey('attributes')
        ->and(json_encode($summary))->not->toContain('secret');
});
