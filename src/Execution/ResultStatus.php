<?php

declare(strict_types=1);

namespace Gcego\Runtime\Execution;

enum ResultStatus: string
{
    case Succeeded = 'succeeded';
    case Failed = 'failed';
}
