<?php

namespace Schemastud\JsonNs\Laravel;

use Schemastud\JsonNs\NamespaceUri;

/**
 * The standard dispatch envelope (asset 06 §6). A small helper that builds the
 * `{value, pointer, namespace, context}` input array the namespace layer hands an
 * {@see NamespaceHandler}, keeping the field names in one place so the socket and its
 * callers cannot drift on the contract.
 *
 * - `value` / `pointer` / `namespace` are **document-derived** — produced by the
 *   framework-free grammar (`schemastud/php-json-ns`), pure and deterministic.
 * - `context` is the **host-runtime passthrough** the Laravel binding fills
 *   (profile / tenant / caller); it is OPAQUE to json-ns, each plug reads what it needs.
 */
class Envelope
{
    private mixed $value;

    private string $pointer;

    private NamespaceUri $namespace;

    /** @var array<string, mixed> */
    private array $context;

    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(mixed $value, string $pointer, NamespaceUri $namespace, array $context = [])
    {
        $this->value = $value;
        $this->pointer = $pointer;
        $this->namespace = $namespace;
        $this->context = $context;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function make(mixed $value, string $pointer, NamespaceUri $namespace, array $context = []): self
    {
        return new self($value, $pointer, $namespace, $context);
    }

    public function value(): mixed
    {
        return $this->value;
    }

    public function pointer(): string
    {
        return $this->pointer;
    }

    public function namespace(): NamespaceUri
    {
        return $this->namespace;
    }

    /** @return array<string, mixed> */
    public function context(): array
    {
        return $this->context;
    }

    /**
     * The wire form handed to {@see NamespaceHandler::invoke()} / `InvocableRegistry::invoke()`.
     * `namespace` is the URI string it matched on.
     *
     * @return array{value: mixed, pointer: string, namespace: string, context: array<string, mixed>}
     */
    public function toArray(): array
    {
        return [
            'value' => $this->value,
            'pointer' => $this->pointer,
            'namespace' => (string) $this->namespace,
            'context' => $this->context,
        ];
    }
}
