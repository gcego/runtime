<?php

declare(strict_types=1);

namespace Gcego\Runtime\Context;

use Illuminate\Http\Request;

/**
 * Descriptive facts about where an execution came from.
 *
 * For tracing and logs only: none of it is ever used to authorize. The id is
 * generated on the server; a client-supplied correlation id is kept separately,
 * and only when it is a short, safe token.
 */
final readonly class RequestMetadata
{
    public const string Http = 'http';

    public const string Console = 'console';

    public const string Internal = 'internal';

    private function __construct(
        public string $id,
        public string $channel,
        public ?string $method = null,
        public ?string $path = null,
        public ?string $ip = null,
        public ?string $correlationId = null,
    ) {}

    public static function fromRequest(Request $request, bool $console = false): self
    {
        $correlation = $request->headers->get('X-Request-Id');

        return new self(
            self::newId(),
            $console && $request->route() === null ? self::Console : self::Http,
            $request->getMethod(),
            mb_substr('/'.ltrim($request->path(), '/'), 0, 255),
            $request->ip(),
            is_string($correlation) && preg_match('/^[A-Za-z0-9._-]{1,64}$/', $correlation) === 1 ? $correlation : null,
        );
    }

    public static function internal(): self
    {
        return new self(self::newId(), self::Internal);
    }

    /**
     * @return array{id: string, channel: string, method: string|null, path: string|null, correlation_id: string|null}
     */
    public function toArray(): array
    {
        return ['id' => $this->id, 'channel' => $this->channel, 'method' => $this->method, 'path' => $this->path, 'correlation_id' => $this->correlationId];
    }

    private static function newId(): string
    {
        return bin2hex(random_bytes(12));
    }
}
