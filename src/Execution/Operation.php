<?php

declare(strict_types=1);

namespace Gcego\Runtime\Execution;

use Gcego\Runtime\Exceptions\InvalidOperationException;
use Gcego\Runtime\Support\Identifiers;
use SplFileInfo;

/**
 * The declarative description of one operation to execute.
 *
 *     Operation::action('delete-subject')->on('blueprint:subjects')->with(['record' => 5]);
 *     Operation::query('students')->with(['search' => 'ada']);
 *     Operation::submit('subject-form')->on('blueprint:subjects')->with($request->all());
 *     Operation::make('workflow', 'approve-leave');
 *
 * An operation says what to do, never who does it: the user, tenant and
 * panel live in the trusted ExecutionContext. The input is untrusted data;
 * handlers declare rules to validate it.
 */
final readonly class Operation
{
    public const string Action = 'action';

    public const string Query = 'query';

    public const string FormSubmission = 'form';

    public const string DefaultName = 'default';

    private const int MaxInputDepth = 16;

    /**
     * @param  array<string, mixed>  $input
     */
    private function __construct(
        public string $type,
        public string $name,
        public ?string $target,
        public array $input,
    ) {}

    /**
     * @throws InvalidOperationException
     */
    public static function make(string $type, string $name = self::DefaultName): self
    {
        foreach (['type' => $type, 'name' => $name] as $field => $value) {
            if (! Identifiers::isName($value)) {
                throw InvalidOperationException::invalid($field, sprintf('"%s" must be a lowercase identifier such as "delete-subject".', mb_substr($value, 0, 100)));
            }
        }

        return new self($type, $name, null, []);
    }

    public static function action(string $name): self
    {
        return self::make(self::Action, $name);
    }

    public static function query(string $name): self
    {
        return self::make(self::Query, $name);
    }

    public static function submit(string $form): self
    {
        return self::make(self::FormSubmission, $form);
    }

    /**
     * The registered definition this operation acts on ("blueprint:subjects").
     *
     * @throws InvalidOperationException for anything but a registry identity.
     */
    public function on(string $target): self
    {
        if (! Identifiers::isDefinition($target)) {
            throw InvalidOperationException::invalid('target', sprintf('"%s" is not a definition identifier. Use "kind:name" (for example "blueprint:subjects") or a bare name.', mb_substr($target, 0, 100)));
        }

        return new self($this->type, $this->name, $target, $this->input);
    }

    /**
     * Replace the (untrusted) input.
     *
     * @param  array<string, mixed>  $input  Nested arrays of scalars, null and uploaded files.
     *
     * @throws InvalidOperationException
     */
    public function with(array $input): self
    {
        self::assertInput($input, 0);

        return new self($this->type, $this->name, $this->target, $input);
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return data_get($this->input, $key, $default);
    }

    /**
     * "type:name", or "type:name@target".
     */
    public function key(): string
    {
        return $this->type.':'.$this->name.($this->target === null ? '' : '@'.$this->target);
    }

    /**
     * Log-safe: the input is left out.
     *
     * @return array{type: string, name: string, target: string|null}
     */
    public function toArray(): array
    {
        return ['type' => $this->type, 'name' => $this->name, 'target' => $this->target];
    }

    /**
     * @param  array<mixed>  $input
     */
    private static function assertInput(array $input, int $depth): void
    {
        if ($depth > self::MaxInputDepth) {
            throw InvalidOperationException::invalid('input', sprintf('must not be nested deeper than %d levels.', self::MaxInputDepth));
        }

        foreach ($input as $key => $value) {
            if ($depth === 0 && ! is_string($key)) {
                throw InvalidOperationException::invalid('input', 'top-level keys must be field names.');
            }

            if (is_array($value)) {
                self::assertInput($value, $depth + 1);
            } elseif (! ($value === null || is_scalar($value) || $value instanceof SplFileInfo)) {
                throw InvalidOperationException::invalid('input', sprintf('"%s" must be a scalar, null, an array or an uploaded file, not %s.', $key, get_debug_type($value)));
            }
        }
    }
}
