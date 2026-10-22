# Architecture

## Responsibilities

```text
gcego/core        packages, lifecycle, extensions, diagnostics
gcego/registry    name -> definition      ("blueprint:subjects" -> Blueprint)
gcego/blueprints  the blueprint definition (references to tables, forms, actions, ...)
gcego/runtime     operation -> handler    (Operation::action('archive') -> ActionHandler)
gcego/rendering   component -> renderer   (built on runtime; declares the "render" category)
```

The runtime executes. It doesn't render, discover definitions, compose
blueprints, query data or decide permissions. Those come in through contracts
that other packages implement.

## Dependency rules

```text
gcego/runtime  ──requires──>  gcego/core, gcego/registry, gcego/blueprints
gcego/rendering ─requires──>  gcego/runtime, gcego/components   (declares "render")
gcego/security ──requires──>  gcego/runtime   (binds ExecutionAuthorizer, TenantResolver)
gcego/actions, gcego/forms, gcego/data adapters ──register──>  handlers
```

- The only GCEGO dependencies are Core, Registry and Blueprints, and the
  only other dependencies are Laravel components. A test asserts this for
  `composer.json` (require and require-dev) and for every `src/` import.
- Security depends on runtime, so runtime only defines authorizer contracts;
  calling Security directly would create a cycle.
- Data integrates through `QueryHandler` adapters that the data package or the
  application registers, usually from a Core extension (`ExecutionExtension`).

## The 15 subsystems

| # | Subsystem | Where |
| --- | --- | --- |
| 1 | Execution kernel | `Runtime::execute()` / `attempt()` → `Execution\ExecutionKernel` |
| 2 | Definition resolver | `Execution\DefinitionResolver`, `ExecutionTarget` (registry + blueprints) |
| 3 | Execution context | `Context\ExecutionContext`, `ExecutionContextFactory`, `TenantReference`, `RequestMetadata` |
| 4 | Request dispatcher | `HandlerRegistry::for()`: exact (type, name), then the category handler |
| 5 | Execution pipeline | `Execution\ExecutionPipeline`: resolve → authorize → handler → authorize → validate → execute |
| 6 | Handler registry | `Execution\HandlerRegistry`: typed categories, conflicts, overrides |
| 7 | Lifecycle manager | `ObservesLifecycle` hooks, `OperationExecuting/Executed/Failed` events, Core `Bootable` |
| 8 | Authorization integration | `ExecutionAuthorizer` (Gate default), `AuthorizesOperation` |
| 9 | Data integration | `QueryHandler` contract; adapters registered by consumers |
| 10 | Extension system | categories, handlers, middleware; `Extensions\ExecutionExtension` on Core's extension manager |
| 11 | Result management | `Execution\ExecutionResult`, `ResultStatus` |
| 12 | Exception handling | `FailureTranslator`, `ExecutionException` + `ErrorCategory`, HTTP rendering |
| 13 | Context isolation | `ExecutionScope` (scoped binding): nesting, subject switching, depth |
| 14 | Diagnostics | `ExecutionTrace`, `RuntimeInspector`, `ExecutionHealthCheck` in `gcego:diagnose` |
| 15 | Laravel integration | `RuntimeServiceProvider`: container, config, views, Core package |

## Lifecycle of an execution

```text
Runtime::execute($operation, ?$context)
  0. context    given, inherited from the running operation, or ExecutionContextFactory::current()
  1. available  gcego/core must have booted (else ErrorCategory::Unavailable)
  2. scope      ExecutionScope::enter(): depth limit, no subject switching
  3. resolve    target identifier -> registry descriptor (nothing loaded yet)
  4. authorize  ExecutionAuthorizer on the descriptor; any failure denies
  5. handler    HandlerRegistry: (type, name), else (type, *)
  6. authorize  handler's AuthorizesOperation, if any
  7. validate   handler's ValidatesOperation rules -> only validated input reaches it
  8. execute    before() -> middleware -> handle() -> succeeded()/failed()
  9. result     ExecutionResult with an ExecutionTrace; or an ExecutionException with a category
```

## Decisions and changes in 1.0

- **Rendering moved to gcego/rendering.** The renderer registry, Blade
  renderers, render context and results, styling, state, the render
  authorizer, views and `<x-...::render>` moved, together with the
  gcego/components dependency. The runtime no longer declares a `render`
  category; gcego/rendering declares it (`RenderEngine::operation()`).
- **Blueprints are references, not components** (gcego/blueprints 1.0).
  Operations target blueprints through `ExecutionTarget::blueprint()`.
- **No Livewire dependency.** The Livewire `BlueprintHost` was removed.
- **Authorization can't be configured away.** Middleware is the configurable
  part of the pipeline, and it always runs after authorization and validation.
- **Default deny everywhere.** No handler opts out of authorization; the
  authorizer decides (guests included).
- **One exception hierarchy with categories** rather than one class per HTTP
  code. Each category maps to a status and a public message.

## Long-running workers

`Runtime`, `ExecutionKernel`, `ExecutionPipeline`, `HandlerRegistry` and
`ExecutionContextFactory` are
singletons that hold only framework-wide data. `ExecutionScope` is a scoped
binding that Laravel discards between requests and jobs, and the kernel resolves it
per call. Contexts, requests, traces and results are per-execution objects.
Tests assert that no `Request`, user or `ExecutionScope` is reachable from
the runtime's singletons after requests, and that memory doesn't grow across
repeated executions.
