# Extension guide: a handler and a data adapter

See [execution](execution.md#handlers). The tested fixtures are
`tests/Fixtures/ArchiveHandler.php` (an action handler with validation),
`FakeDataSource.php` with `DataQueriesExtension.php` (a query adapter
activated through gcego/core's extension system) and
`HostTenantResolver.php` (a server-side tenant resolver). Checklist:

- **Contract:** implement your category's contract (`ActionHandler`,
  `QueryHandler`, `FormSubmissionHandler`), or declare a category.
- **Statelessness:** read everything from the `ExecutionRequest`.
- **Validation:** declare rules (`ValidatesOperation`); read input only
  through `$request->input()`.
- **Scoping:** scope data by `$request->context->tenant` and user, never by input.
- **Loading:** load the target with `$request->target()->definition()` or
  `blueprint()` only when you need it.
- **Errors:** throw `ValidationException`, `AuthorizationException` or an
  `ExecutionException`; anything else becomes `handler_failed`.

Rendering extensions (renderers, Blade views, styling slots) are documented in
gcego/rendering.
