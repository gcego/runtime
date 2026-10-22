<?php

declare(strict_types=1);

namespace Gcego\Runtime\Contracts;

/**
 * Handler contract for "query" operations (Operation::Query).
 *
 * Runs data queries. The runtime does not depend on gcego/data: the data package or the application registers an adapter, so query operations are unsupported until one is installed.
 */
interface QueryHandler extends OperationHandler {}
