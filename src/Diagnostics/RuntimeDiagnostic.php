<?php

declare(strict_types=1);

namespace Gcego\Runtime\Diagnostics;

/**
 * One structured runtime finding.
 */
final readonly class RuntimeDiagnostic
{
    public const string Ok = 'ok';

    public const string Warning = 'warning';

    public const string Error = 'error';

    /**
     * @param  'ok'|'warning'|'error'  $level
     * @param  array<string, scalar|list<scalar>|null>  $details
     */
    public function __construct(
        public string $check,
        public string $level,
        public string $message,
        public array $details = [],
    ) {}

    /**
     * @return array{check: string, level: string, message: string, details: array<string, scalar|list<scalar>|null>}
     */
    public function toArray(): array
    {
        return ['check' => $this->check, 'level' => $this->level, 'message' => $this->message, 'details' => $this->details];
    }
}
