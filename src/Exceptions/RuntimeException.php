<?php

declare(strict_types=1);

namespace Gcego\Runtime\Exceptions;

use Throwable;

/**
 * Marker implemented by every exception thrown by gcego/runtime.
 *
 * Authorization failures are Laravel's own AuthorizationException (HTTP 403).
 */
interface RuntimeException extends Throwable {}
