# Changelog

All notable changes to `gcego/runtime` are documented in this file.
The project follows [Semantic Versioning](https://semver.org/).

## [Unreleased]

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
