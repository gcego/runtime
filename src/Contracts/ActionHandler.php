<?php

declare(strict_types=1);

namespace Gcego\Runtime\Contracts;

/**
 * Handler contract for "action" operations (Operation::Action).
 *
 * Executes actions. gcego/actions registers an adapter around its ActionExecutionEngine; applications may register handlers for single actions by name.
 */
interface ActionHandler extends OperationHandler {}
