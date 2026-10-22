<?php

declare(strict_types=1);

namespace Gcego\Runtime\Contracts;

use Gcego\Runtime\Execution\ExecutionRequest;

/**
 * A handler that validates the operation input with Laravel validation rules.
 *
 * Runs after authorization. Only validated fields reach the handler through
 * ExecutionRequest::input().
 */
interface ValidatesOperation
{
    /**
     * @return array<string, mixed>
     */
    public function rules(ExecutionRequest $request): array;
}
