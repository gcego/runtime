# Security

## Trusted contexts

- **User:** always taken from the authentication guard, never from an id in input.
- **Tenant:** comes only from a `TenantResolver` that verifies membership on
  the server. Request parameters and headers such as `tenant` or `X-Tenant`
  are ignored; a test sends both and asserts the resolver's tenant wins.
- **Panel:** comes only from a `PanelResolver`.
- **System context:** `ExecutionContext::system()` exists only in code. The
  factory never produces it from a request.
- **Request metadata** (path, IP, correlation id) is descriptive and is never
  used to authorize. Client correlation ids are accepted only as short safe tokens.
- **Immutable:** contexts can't be changed after creation; `withAttributes()` never
  touches user, tenant, panel or privilege.

## Execution authorization (fails closed)

| Stage | Behaviour |
| --- | --- |
| `ExecutionAuthorizer` | runs for **every** operation, before validation and before the target loads; the default asks the Gate for `gcego-runtime.execute` **as the context's user** |
| undefined ability | denied (Laravel denies undefined abilities) |
| authorizer throws anything else | denied as `forbidden`, the cause reported |
| `AuthorizesOperation` on the handler | must also allow; false or an exception denies |
| guests | `unauthenticated` (401); signed-in users `forbidden` (403) |

Denied callers learn nothing about which handlers exist: authorization runs
before handler lookup, and validation runs after authorization. Middleware
can't run for denied operations.

## Isolation

- `ExecutionScope` is scoped per request/job and emptied in a `finally` block.
- A nested operation must keep its parent's user, tenant and privilege.
  Only a system parent may act for another subject. Nesting is limited by
  `execution.max_depth` (8).
- Shared services hold no request, user or scope (asserted by tests).

## Errors

Execution failures render only the category's public message (plus
validation errors), whatever `app.debug` says. Traces and `toArray()`
summaries hold identifiers, never input or user objects.

## Identities, not class names

Operation types, names and targets are identifiers. Targets are resolved only
through gcego/registry, whose loader only executes files inside configured
discovery roots. Handler classes come only from server-side registration, and
operation input can't contain objects.

## Nesting

Operations started from handlers are limited by `execution.max_depth` (8 by
default) and must keep their parent's subject.
