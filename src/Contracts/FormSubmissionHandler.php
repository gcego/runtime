<?php

declare(strict_types=1);

namespace Gcego\Runtime\Contracts;

/**
 * Handler contract for "form" operations (Operation::FormSubmission).
 *
 * Processes form submissions. Owned by gcego/forms; the runtime defines only the contract.
 */
interface FormSubmissionHandler extends OperationHandler {}
