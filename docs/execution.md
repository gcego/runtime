# Execution

## Operations

```php
use Gcego\Runtime\Execution\Operation;

Operation::action('delete-subject')->on('blueprint:subjects')->with(['record' => 5]);
Operation::query('students')->with(['search' => 'ada']);
Operation::submit('subject-form')->on('blueprint:subjects')->with($request->all());
Operation::make('workflow', 'approve-leave');            // a package-defined category
```

An `Operation` is immutable. It says **what** to do (`type`, `name`, optional
`target`, untrusted `input`) and never **who** does it. Types and names are
lowercase identifiers. Targets must be registry identities, never class names
or paths. Input is nested arrays of scalars, null and uploaded files, up to
16 levels deep. `toArray()` leaves the input out, so it is safe to log.

## Executing

```php
use Gcego\Runtime\Facades\Runtime;

$result = Runtime::execute($operation);              // throws ExecutionException on failure
$result = Runtime::attempt($operation);              // failures come back as results
$result = Runtime::execute($operation, $context);    // an explicit, trusted context
```

The `Runtime` facade (auto-aliased as `\Runtime`) resolves
`Contracts\RuntimeInterface`, so tests can fake the kernel although the
`Gcego\Runtime\Runtime` class is final:

```php
Runtime::shouldReceive('execute')->once()->andReturn(ExecutionResult::success('faked'));
```

| `ExecutionResult` | |
| --- | --- |
| `status`, `succeeded()`, `failed()` | `ResultStatus::Succeeded` / `Failed` |
| `data()` | the handler's return value |
| `meta` | scalar metadata a handler set with `ExecutionResult::success($data, $meta)` |
| `error`, `category()`, `throw()` | the `ExecutionException` of a failed attempt |
| `trace` | the `ExecutionTrace` |
| `toArray()`, JSON | safe for API responses: failures show only the public message and validation errors |

Returning a result from a controller produces JSON. A thrown
`ExecutionException` renders through Laravel's handler: 401 (login redirect or
JSON), 403, 422 with field errors, 404, 501, 500 or 503, always with a public
message and never the internal one.

## Contexts

```php
use Gcego\Runtime\Context\ExecutionContext;
use Gcego\Runtime\Context\ExecutionContextFactory;

$contexts = Runtime::contexts();                   // or app(ExecutionContextFactory::class)

$contexts->current();              // the current request: guard user, resolved tenant and panel
$contexts->forUser($user, $tenant, 'admin');
$contexts->system();               // trusted server code (jobs, commands); allowed by the default authorizer
```

| Source | Value |
| --- | --- |
| user | `Auth::guard()->user()`, never an id from input |
| tenant | `Contracts\TenantResolver` (default: none); must verify membership on the server |
| panel | `Contracts\PanelResolver` (default: none); from routing configuration |
| locale | the application locale |
| request | `RequestMetadata`: server-generated id, channel, method, path, IP, a safe correlation id |

Contexts are immutable. `withAttributes()` adds descriptive scalars and never
changes user, tenant, panel or privilege. Without an explicit context,
`execute()` uses the running operation's context (for nested operations) or
the current request's.

## Handlers

```php
use Gcego\Runtime\Contracts\ActionHandler;
use Gcego\Runtime\Contracts\ValidatesOperation;
use Gcego\Runtime\Execution\ExecutionRequest;

final class ArchiveSubject implements ActionHandler, ValidatesOperation
{
    public function rules(ExecutionRequest $request): array
    {
        return ['record' => ['required', 'integer']];
    }

    public function handle(ExecutionRequest $request): mixed
    {
        $blueprint = $request->target()->blueprint();      // loaded only now, after authorization
        // ... uses $request->input('record') and $request->context->tenant

        return ['archived' => $request->input('record')];
    }
}

app(HandlerRegistry::class)->register(Operation::Action, ArchiveSubject::class, name: 'archive-subject');
```

| Category | Contract | Provided by |
| --- | --- | --- |
| `action` | `ActionHandler` | gcego/actions adapter, or applications per action name |
| `query` | `QueryHandler` | a gcego/data adapter or the application; unsupported until one is installed |
| `form` | `FormSubmissionHandler` | gcego/forms |
| custom | declared with `HandlerRegistry::category('workflow', WorkflowHandler::class)` | the declaring package, for example gcego/rendering declares `render` |

The registry rejects handlers that don't implement their category's contract.
A handler registered for `(type, name)` wins over one for the whole category.
Registering a second handler for the same key throws unless you pass
`override: true` (the inspector lists overrides). Handlers are built once
through the container and must be stateless.

Optional interfaces:

| Interface | Effect |
| --- | --- |
| `ValidatesOperation::rules()` | Laravel rules, applied after authorization; `input()` then returns only validated fields |
| `AuthorizesOperation::authorize()` | an extra check after the `ExecutionAuthorizer`; false or an exception denies |
| `ObservesLifecycle` | `before()` (may fail the operation), `succeeded()`, `failed()` (exceptions reported, never change the outcome) |

## The pipeline

```text
resolve -> authorize -> handler -> authorize.handler -> validate -> execute (middleware + handler)
```

The trace records each stage that ran, with timings. Middleware
(`Contracts\ExecutionMiddleware`) wraps handlers for transactions, auditing,
rate limits or caching. Add it with `gcego-runtime.execution.middleware`
(checked when the package boots) or `ExecutionPipeline::middleware()`. It runs
only after authorization and validation.

## Errors

Every failure is an `ExecutionException` with an `ErrorCategory`:

| Category | Status | Exception | Typical cause |
| --- | --- | --- | --- |
| `invalid_operation` | 400 | `InvalidOperationException` | malformed operation, missing or wrong target |
| `unauthenticated` | 401 | `ExecutionDeniedException` | a guest was denied |
| `forbidden` | 403 | `ExecutionDeniedException` | denied, or authorization could not be established |
| `not_found` | 404 | `TargetNotFoundException` | the target is not registered |
| `validation_failed` | 422 | `OperationValidationException` (`errors()`) | handler rules, or a `ValidationException` from the handler |
| `unsupported` | 501 | `OperationNotSupportedException` | no handler, or an unknown category |
| `context_violation` | 500 | `ContextViolationException` | nested subject switch, nesting too deep |
| `handler_failed` | 500 | `HandlerFailedException` | anything else a handler, hook or middleware threw (`getPrevious()`) |
| `unavailable` | 503 | `ExecutionException` | gcego/core has not booted (or GCEGO is disabled) |

Client categories (4xx) are not reported. `attempt()` reports server
categories to Laravel's exception handler.

## Events

`Execution\Events\OperationExecuting` (before authorization),
`OperationExecuted` (with the result) and `OperationFailed` (with the
exception and its trace) are dispatched through Laravel's event dispatcher.

## Extending through gcego/core

```php
final class DataQueries extends ExecutionExtension
{
    public function descriptor(): ExtensionDescriptor
    {
        return new ExtensionDescriptor('data.queries', whenPackages: ['gcego/runtime', 'gcego/data'], capabilities: ['data.queries']);
    }

    protected function extend(HandlerRegistry $handlers, ExecutionPipeline $pipeline): void
    {
        $handlers->register(Operation::Query, DataQueryHandler::class);
    }
}

// The owning package's provider, register():
$this->app->make(ExtensionManager::class)->register(new DataQueries);
```

Core activates the extension once, after every package has booted, and skips
it when a `whenPackages` package is inactive. `tests/Feature/ExtensionTest.php`
does exactly this with an in-memory data source.
