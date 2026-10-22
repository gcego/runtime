# Changelog

All notable changes to `gcego/runtime` are documented in this file.
The project follows [Semantic Versioning](https://semver.org/).

## [1.0.0] - 2026-10-22

### Added: execution kernel

- `Facades\Runtime` (auto-aliased as `\Runtime`) over the new `Contracts\RuntimeInterface`, mockable with `shouldReceive()`.
- `Runtime::execute()`, `attempt()` and `contexts()`, backed by `Execution\ExecutionKernel` and `ExecutionPipeline` (resolve → authorize → handler → handler authorization → validation → middleware → handler).
- `Execution\Operation` (`make()`, `action()`, `query()`, `submit()`, `on()`, `with()`): immutable, identifier-only targets, data-only input.
- Trusted, immutable `Context\ExecutionContext` (user, tenant, panel, locale, request metadata, system flag) built by `ExecutionContextFactory` from the guard and the new `TenantResolver` / `PanelResolver` contracts, never from request input.
- Typed `Execution\HandlerRegistry` with the categories `action`, `query`, `form` and package-defined categories; contracts `OperationHandler`, `ActionHandler`, `QueryHandler`, `FormSubmissionHandler`, `ValidatesOperation`, `AuthorizesOperation`, `ObservesLifecycle`, `ExecutionMiddleware`.
- Fail-closed `ExecutionAuthorizer` contract with the Gate-based `GateExecutionAuthorizer` (`gcego-runtime.execute`, checked as the context's user).
- `ExecutionResult`, `ExecutionTrace`, and the events `OperationExecuting`, `OperationExecuted`, `OperationFailed`.
- `ExecutionException` with `ErrorCategory` (HTTP status and public message) and the subclasses `InvalidOperationException`, `ExecutionDeniedException`, `TargetNotFoundException`, `OperationValidationException`, `OperationNotSupportedException`, `ContextViolationException`, `HandlerFailedException`; `HandlerRegistrationException`.
- Context isolation through the scoped `ExecutionScope`: nested operations keep their subject, nesting is limited (`execution.max_depth`).
- gcego/core integration: `Extensions\ExecutionExtension` for Core extensions, `Bootable::bootPackage()` adds configured middleware, `gcego-runtime.execution` diagnostic, refusal to execute before Core has booted.
- `RuntimeInspector` reports the framework state, handlers, categories, overrides, the authorizer and middleware (`RuntimeDiagnostic`, `RuntimeReport::$handlers`).
- Configuration `gcego-runtime.execution.ability`, `.middleware`, `.max_depth`.

### Changed (breaking)

- gcego/runtime is now the execution kernel only: it depends on `gcego/core` ^1.0, `gcego/registry` ^1.1, `gcego/blueprints` ^1.0 and Laravel components. `gcego/components`, `livewire/livewire` and `illuminate/view` are no longer required.
- **Rendering moved to the new gcego/rendering package** (namespace `Gcego\Rendering`, see its README for the new class locations): `Rendering\*`, `Blade\BladeRenderer`, `Styling\*`, `State\*`, `Contracts\RendererInterface`, `StylingResolver`, `RenderAuthorizer`, `StatefulComponent`, `Security\GateRenderAuthorizer`, the rendering exceptions, the views and `<x-gcego-runtime::render>` (now `<x-gcego-rendering::render>`). `Runtime::render()`, `renderAuthorized()`, `identityOf()`, `canRender()` and `hostedBlueprint()` are now on `Gcego\Rendering\RenderEngine` (`hostedBlueprint()` is `authorizedBlueprint()`). The `gcego-runtime.rendering.*` and `gcego-runtime.authorization.*` settings are now `gcego-rendering.*`; the ability is `gcego-rendering.render`.
- `RuntimeInspector::inspect()` takes no target and reports execution only; `RuntimeReport` lost `renderers` and `missingRenderers`; `RenderDiagnostic` is `RuntimeDiagnostic`.
- `RuntimeConfig` holds execution settings only and throws `InvalidConfigurationException`.
- The package descriptor requires `gcego/blueprints`; capabilities are `runtime` and `runtime.execution` (`runtime.livewire` was removed).

### Removed

- `Rendering\BlueprintRenderer`, `Livewire\BlueprintHost`, the `renderers.blueprint` and `hosts.blueprint` views and the `gcego-runtime.livewire.*` configuration (blueprints are no longer components in gcego/blueprints 1.0).

## [0.1.0] - 2026-10-22

### Added

- `Runtime` entry point: render components or registered definitions (by identity), with an authorized variant for public entry points.
- `RendererRegistry` with explicit precedence (exact class, parent class, interface), conflict detection and `override: true`; renderers built once via the container.
- `ComponentRenderer` with depth and cycle guards and a strict/non-strict error boundary; lazy `Children`; immutable `RenderContext`; `RenderResult` with an explicit escaping policy.
- `BladeRenderer` base class, built-in `BlueprintRenderer` and `ContainerRenderer`, the `gcego-runtime` view namespace, and `<x-gcego-runtime::render>`.
- Livewire 4 `BlueprintHost` (`<livewire:gcego-blueprint>`) with a locked identity, per-request authorization and validated state updates; `StatefulComponent`, `StatePath` and `StateCoordinator`.
- `StylingResolver` contract with `StyleSlots` and the default `NullStylingResolver`.
- `RenderAuthorizer` contract with the fail-closed `GateRenderAuthorizer`.
- `RuntimeInspector` diagnostics.
- `RuntimeServiceProvider` (a `gcego/core` package).
