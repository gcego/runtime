<?php

declare(strict_types=1);

namespace Gcego\Runtime\Context;

use Gcego\Runtime\Exceptions\InvalidOperationException;
use Gcego\Runtime\Support\Identifiers;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * The trusted, immutable "who, where and for whom" of one execution.
 *
 * User, tenant and panel come from server-side sources only: the
 * authentication guard, a TenantResolver and a PanelResolver (see
 * ExecutionContextFactory), or trusted server code. Nothing here is read
 * from request input, and a context can't be changed after creation: the
 * with*() methods only add descriptive attributes.
 *
 * A context is created per execution and never stored in shared services.
 */
final readonly class ExecutionContext
{
    /**
     * @param  array<string, scalar|null>  $attributes
     */
    private function __construct(
        public ?Authenticatable $user,
        public ?TenantReference $tenant,
        public ?string $panel,
        public string $locale,
        public RequestMetadata $request,
        public bool $system,
        public array $attributes,
    ) {
        if ($panel !== null && ! Identifiers::isName($panel)) {
            throw InvalidOperationException::invalid('context panel', 'must be a lowercase identifier such as "admin".');
        }

        if (preg_match(Identifiers::LocalePattern, $locale) !== 1) {
            throw InvalidOperationException::invalid('context locale', 'must be a locale such as "en" or "fr_CM".');
        }

        foreach ($attributes as $key => $value) {
            if (! Identifiers::isName($key) || ! ($value === null || is_scalar($value))) {
                throw InvalidOperationException::invalid('context attributes', 'keys must be lowercase identifiers and values scalars or null.');
            }
        }
    }

    /**
     * An authenticated user's context.
     *
     * @param  array<string, scalar|null>  $attributes
     */
    public static function forUser(Authenticatable $user, ?TenantReference $tenant = null, ?string $panel = null, string $locale = 'en', ?RequestMetadata $request = null, array $attributes = []): self
    {
        return new self($user, $tenant, $panel, $locale, $request ?? RequestMetadata::internal(), false, $attributes);
    }

    /**
     * An anonymous visitor's context.
     *
     * @param  array<string, scalar|null>  $attributes
     */
    public static function guest(?TenantReference $tenant = null, ?string $panel = null, string $locale = 'en', ?RequestMetadata $request = null, array $attributes = []): self
    {
        return new self(null, $tenant, $panel, $locale, $request ?? RequestMetadata::internal(), false, $attributes);
    }

    /**
     * A privileged context for trusted server code (jobs, commands, seeders).
     *
     * The default authorizer allows system contexts. Never create one on
     * behalf of an HTTP request.
     *
     * @param  array<string, scalar|null>  $attributes
     */
    public static function system(?TenantReference $tenant = null, ?string $panel = null, string $locale = 'en', array $attributes = []): self
    {
        return new self(null, $tenant, $panel, $locale, RequestMetadata::internal(), true, $attributes);
    }

    /**
     * A copy with more descriptive attributes. User, tenant, panel and privilege never change.
     *
     * @param  array<string, scalar|null>  $attributes
     */
    public function withAttributes(array $attributes): self
    {
        return new self($this->user, $this->tenant, $this->panel, $this->locale, $this->request, $this->system, [...$this->attributes, ...$attributes]);
    }

    public function isSystem(): bool
    {
        return $this->system;
    }

    public function isGuest(): bool
    {
        return ! $this->system && $this->user === null;
    }

    public function userId(): string|int|null
    {
        $id = $this->user?->getAuthIdentifier();

        return is_string($id) || is_int($id) ? $id : null;
    }

    public function attribute(string $key, string|int|float|bool|null $default = null): string|int|float|bool|null
    {
        return $this->attributes[$key] ?? $default;
    }

    /**
     * Same user (by class and identifier), same tenant and same privilege.
     */
    public function sameSubjectAs(self $other): bool
    {
        return $this->system === $other->system
            && ($this->user === null ? null : $this->user::class) === ($other->user === null ? null : $other->user::class)
            && $this->userId() === $other->userId()
            && ($this->tenant === null ? $other->tenant === null : $this->tenant->equals($other->tenant));
    }

    /**
     * A log-safe summary: identifiers only, no user object or attributes.
     *
     * @return array{system: bool, user: string|int|null, tenant: string|null, panel: string|null, locale: string, request: array{id: string, channel: string, method: string|null, path: string|null, correlation_id: string|null}}
     */
    public function toArray(): array
    {
        return [
            'system' => $this->system,
            'user' => $this->userId(),
            'tenant' => $this->tenant?->toString(),
            'panel' => $this->panel,
            'locale' => $this->locale,
            'request' => $this->request->toArray(),
        ];
    }
}
