<?php

declare(strict_types=1);

namespace Gcego\Runtime\Tests\Fixtures;

use Gcego\Runtime\Contracts\ActionHandler;
use Gcego\Runtime\Contracts\ValidatesOperation;
use Gcego\Runtime\Execution\ExecutionRequest;

/**
 * An action handler with validation: archives a record and echoes what it received.
 */
final class ArchiveHandler implements ActionHandler, ValidatesOperation
{
    public function rules(ExecutionRequest $request): array
    {
        return ['record' => ['required', 'integer', 'min:1'], 'reason' => ['sometimes', 'string', 'max:20']];
    }

    /**
     * @return array{archived: mixed, input: mixed, user: string|int|null}
     */
    public function handle(ExecutionRequest $request): array
    {
        return ['archived' => $request->input('record'), 'input' => $request->input(), 'user' => $request->context->userId()];
    }
}
