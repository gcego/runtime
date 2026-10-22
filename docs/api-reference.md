# API reference

All classes live under `Gcego\Runtime`.

## `Runtime` (final readonly, implements `Contracts\RuntimeInterface`)

Also available as the facade `Facades\Runtime` (alias `\Runtime`), which
resolves `RuntimeInterface` and mocks it with `shouldReceive()`.

| Method | Notes |
| --- | --- |
| `execute(Operation $operation, ?ExecutionContext $context = null): ExecutionResult` | throws `ExecutionException`; context defaults to the running operation's, else the current request's |
| `attempt(Operation $operation, ?ExecutionContext $context = null): ExecutionResult` | failures as results |
| `contexts(): ExecutionContextFactory` | |

## Contracts

| Interface | Methods |
| --- | --- |
| `Contracts\RuntimeInterface` | `execute()`, `attempt()`, `contexts()` |
| `Contracts\OperationHandler` | `handle(ExecutionRequest): mixed` |
| `Contracts\ActionHandler`, `QueryHandler`, `FormSubmissionHandler` | category contracts (extend `OperationHandler`) |
| `Contracts\ValidatesOperation` | `rules(ExecutionRequest): array` |
| `Contracts\AuthorizesOperation` | `authorize(ExecutionRequest): bool` |
| `Contracts\ObservesLifecycle` | `before()`, `succeeded()`, `failed()` |
| `Contracts\ExecutionAuthorizer` | `authorize(ExecutionRequest): void` (throws `AuthorizationException`/`AuthenticationException`) |
| `Contracts\ExecutionMiddleware` | `handle(ExecutionRequest, Closure $next): ExecutionResult` |
| `Contracts\TenantResolver` | `resolve(?Authenticatable, Request): ?TenantReference` |
| `Contracts\PanelResolver` | `resolve(Request): ?string` |

## Execution

| Class | Members |
| --- | --- |
| `Execution\Operation` | `static make(type, name = 'default')`, `action()`, `query()`, `submit()`; `on(target)`, `with(input)`, `input(key)`, `key()`, `toArray()`; public `type`, `name`, `target`, `input`; constants `Action`, `Query`, `FormSubmission` |
| `Execution\ExecutionKernel` | `execute()`, `attempt()`, `currentContext()` |
| `Execution\ExecutionPipeline` | `middleware(string\|ExecutionMiddleware ...)`, `middlewareClasses()`, `process()` |
| `Execution\HandlerRegistry` | `category(type, contract)`, `register(type, handler, ?name, override)`, `has()`, `hasCategory()`, `bindingFor()`, `for()`, `categories()`, `bindings()`, `overrides()` |
| `Execution\HandlerBinding` | public `type`, `name`, `handler`; `key()`, `handlerClass()`, `toArray()` |
| `Execution\ExecutionRequest` | public `operation`, `context`, `target`, `trace`, `validated`; `target()`, `input(?key)`, `isValidated()` |
| `Execution\ExecutionTarget` | public `descriptor`; `identity()`, `kind()`, `isBlueprint()`, `isLoaded()`, `definition()`, `definitionAs(class)`, `blueprint()` |
| `Execution\ExecutionResult` | `static success(data, meta)`, `static failure(exception)`; `succeeded()`, `failed()`, `data()`, `category()`, `throw()`, `toArray()`; public `status`, `data`, `error`, `meta`, `trace` |
| `Execution\ExecutionTrace` | public `id`, `operation`, `requestId`, `depth`; `stages()`, `stageNames()`, `handler()`, `status()`, `category()`, `durationMs()`, `toArray()` |
| `Execution\ExecutionScope` | scoped: `current()`, `depth()`, `isActive()` |
| `Execution\DefinitionResolver`, `FailureTranslator` | internal services |
| `Execution\Events\OperationExecuting`, `OperationExecuted`, `OperationFailed` | events |
| `Context\ExecutionContext` | `static forUser()`, `guest()`, `system()`; `withAttributes()`, `isSystem()`, `isGuest()`, `userId()`, `attribute()`, `sameSubjectAs()`, `toArray()`; public `user`, `tenant`, `panel`, `locale`, `request`, `system`, `attributes` |
| `Context\ExecutionContextFactory` | `current()`, `fromRequest()`, `forUser()`, `system()` |
| `Context\TenantReference` | public `key`, `type`; `equals()`, `toString()` |
| `Context\RequestMetadata` | public `id`, `channel`, `method`, `path`, `ip`, `correlationId` |
| `Extensions\ExecutionExtension` (abstract) | Core extension; implement `descriptor()` and `extend(HandlerRegistry, ExecutionPipeline)` |

## Security and support

| Class | Members |
| --- | --- |
| `Security\GateExecutionAuthorizer` | default `ExecutionAuthorizer` |
| `Support\Identifiers` | `isName()`, `isDefinition()`; constants `NamePattern`, `LocalePattern` |

## Diagnostics

| Class | Members |
| --- | --- |
| `Diagnostics\RuntimeInspector` | `inspect(): RuntimeReport` (never throws) |
| `Diagnostics\RuntimeReport` | public `diagnostics`, `handlers`; `check(string)`, `isHealthy()`, `toArray()` |
| `Diagnostics\RuntimeDiagnostic` | public `check`, `level`, `message`, `details`; `toArray()` |
| `Diagnostics\ExecutionHealthCheck` | Core `DiagnosticCheck` `gcego-runtime.execution` |

Checks: `config`, `execution.framework`, `execution.categories`,
`execution.override`, `execution.authorizer`, `execution.middleware`.

## Configuration (`RuntimeConfig`)

| Key | Default |
| --- | --- |
| `gcego-runtime.execution.ability` | `gcego-runtime.execute` |
| `gcego-runtime.execution.middleware` | `[]` |
| `gcego-runtime.execution.max_depth` | `8` |

## Exceptions (all implement `Exceptions\RuntimeException`)

`ExecutionException` (with `ErrorCategory`, `status()`, `trace()`,
`toArray()`, `render()`) and its subclasses `InvalidOperationException`,
`ExecutionDeniedException`, `TargetNotFoundException`,
`OperationValidationException`, `OperationNotSupportedException`,
`ContextViolationException` and `HandlerFailedException`. Registration errors
are `HandlerRegistrationException`, configuration errors
`InvalidConfigurationException`.

## Service provider

`RuntimeServiceProvider` (implements `Gcego\Core\Contracts\PackageInterface` and `Bootable`; requires `gcego/core`, `gcego/registry`, `gcego/blueprints`; capabilities `runtime`, `runtime.execution`):

- publish tag `gcego-runtime-config`;
- singletons `Runtime` (aliased as `RuntimeInterface`), `RuntimeConfig`, `HandlerRegistry`, `ExecutionPipeline`,
  `ExecutionKernel`, `ExecutionContextFactory`, `ExecutionAuthorizer`,
  `TenantResolver`, `PanelResolver`, `DefinitionResolver`, `FailureTranslator`;
  scoped `ExecutionScope`;
- `bootPackage()` adds the configured middleware;
- the `gcego-runtime.execution` diagnostic.
