<?php

declare(strict_types=1);

namespace Gcego\Runtime\Exceptions;

use Throwable;

/**
 * Marker implemented by every exception thrown by gcego/runtime.
 *
 * Executed operations throw ExecutionException subclasses, each with an
 * ErrorCategory; registration and configuration errors are
 * HandlerRegistrationException and InvalidConfigurationException.
 */
interface RuntimeException extends Throwable {}
