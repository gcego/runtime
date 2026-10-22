<?php

declare(strict_types=1);

namespace Gcego\Runtime\Tests\Fixtures;

use Gcego\Runtime\Contracts\QueryHandler;
use Gcego\Runtime\Contracts\ValidatesOperation;
use Gcego\Runtime\Execution\ExecutionRequest;

/**
 * Stands in for a gcego/data adapter: answers query operations from an in-memory source.
 * A real adapter would call DataManager with the context's user and tenant.
 */
final readonly class FakeDataSource implements QueryHandler, ValidatesOperation
{
    private const array Students = [
        ['id' => 1, 'name' => 'Ada', 'tenant' => 1],
        ['id' => 2, 'name' => 'Alan', 'tenant' => 1],
        ['id' => 3, 'name' => 'Grace', 'tenant' => 2],
    ];

    public function rules(ExecutionRequest $request): array
    {
        return ['search' => ['sometimes', 'string', 'max:50']];
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    public function handle(ExecutionRequest $request): array
    {
        $tenant = $request->context->tenant?->key;
        $search = $request->input('search', '');

        $rows = array_filter(self::Students, static fn (array $row): bool => (string) $row['tenant'] === (string) $tenant && str_contains(strtolower($row['name']), strtolower(is_string($search) ? $search : '')));

        return array_values(array_map(static fn (array $row): array => ['id' => $row['id'], 'name' => $row['name']], $rows));
    }
}
