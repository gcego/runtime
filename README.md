# gcego/runtime

The execution kernel of GCEGO Studio. One entry point executes every kind of
framework operation (actions, data queries, form submissions and operations of
package-defined categories) with a trusted context, fail-closed authorization,
validation, standardized results and diagnostics.

```php
use Gcego\Runtime\Execution\Operation;
use Gcego\Runtime\Facades\Runtime;      // auto-aliased as \Runtime

$result = Runtime::execute(
    Operation::action('archive-subject')->on('blueprint:subjects')->with(['record' => 5]),
);                                   // runs as the signed-in user, tenant and panel

$result->data();                     // whatever the handler returned
```

**gcego/runtime = gcego/core + gcego/registry + gcego/blueprints + Laravel.**
Everything specialized integrates through contracts that runtime owns:

| Package | Integrates through |
| --- | --- |
| gcego/data | `QueryHandler` adapters (usually from a Core `ExecutionExtension`) |
| gcego/security | `ExecutionAuthorizer`, `TenantResolver` |
| gcego/actions | `ActionHandler` |
| gcego/forms | `FormSubmissionHandler` |
| gcego/panels | `PanelResolver` |
| gcego/rendering | declares the `render` category and its handler |

The runtime renders nothing. Component rendering (renderer registry, Blade,
`<x-gcego-rendering::render>`) lives in `gcego/rendering`.

## Requirements

PHP 8.3+, Laravel 13, `gcego/core` ^1.0, `gcego/registry` ^1.1 and
`gcego/blueprints` ^1.0. There is no dependency on gcego/components, gcego/data,
gcego/security, Livewire or React.

## Installation

```bash
composer require gcego/runtime
php artisan vendor:publish --tag=gcego-runtime-config   # optional
```

The provider is auto-discovered and is the `gcego/runtime` package of
`gcego/core`, after `gcego/core`, `gcego/registry` and `gcego/blueprints`. No
routes or views are registered.

## Authorization: required before anything runs

Every operation passes the `ExecutionAuthorizer`. The default asks the Gate
for an ability that Laravel denies until you define it:

```php
Gate::define('gcego-runtime.execute', fn (?User $user, ExecutionRequest $request): bool => ...);
```

Users, tenants and panels are never read from request input. See
[security](docs/security.md).

## Documentation

| | |
| --- | --- |
| [Architecture](docs/architecture.md) | Responsibilities, the 15 subsystems, decisions, dependency rules |
| [Execution](docs/execution.md) | Operations, contexts, handlers, pipeline, results, errors, extensions |
| [Security](docs/security.md) | Contexts, authorization, isolation, errors |
| [Extension guide](docs/extension-guide.md) | A handler and a data adapter |
| [API reference](docs/api-reference.md) | Every public class and method |

## Development

```bash
composer test       # Pest + Testbench
composer analyse    # PHPStan (Larastan), level max
composer format     # Pint
composer benchmark  # handler lookup, kernel overhead, memory
```

## License

The MIT License (MIT). See [LICENSE.md](LICENSE.md).
