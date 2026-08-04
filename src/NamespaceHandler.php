<?php

namespace Schemastud\JsonNs\Laravel;

use Rushing\Popcorn\Binding;
use Rushing\Popcorn\Contracts\Invocable;

/**
 * The URI-keyed dispatch socket: a popcorn {@see Invocable} whose {@see name()} IS a bound
 * namespace URI (asset 06 §3). A host binds its own `URI → handler` by wrapping a plain
 * callback as one of these and registering it into the `InvocableRegistry` — no new port,
 * no parallel registry; the namespace layer is simply the registry's third consumer (§3).
 *
 * The wrapped callback receives the standard envelope (§6):
 *
 *   invoke([
 *     'value'     => <the namespaced node — the CURIE key's value, or the object node>,
 *     'pointer'   => "/config/grounding",   // JSON Pointer to it
 *     'namespace' => "https://schemas.splicewire.app/splice/grounding",  // the matched URI
 *     'context'   => [ ... ],               // OPAQUE host-runtime passthrough (profile/tenant/caller)
 *   ]) : array
 *
 * `value`/`pointer`/`namespace` are document-derived (produced by the framework-free grammar,
 * pure); `context` is the host-runtime bag the Laravel tier fills and json-ns never inspects.
 *
 * Because the URI is identity (asset 05 §5), a CURIE key and an `@namespaced` object that
 * resolve to the SAME URI bind to the SAME handler — one registration serves both attachment
 * points.
 */
class NamespaceHandler implements Invocable
{
    private string $uri;

    /** @var callable(array<string, mixed>): array<string, mixed> */
    private $handle;

    private Binding $binding;

    /**
     * @param  string  $uri  the namespace URI this handler binds to (its registry name)
     * @param  callable(array<string, mixed>): array<string, mixed>  $handle  the host plug
     */
    public function __construct(string $uri, callable $handle, Binding $binding = Binding::Local)
    {
        $this->uri = $uri;
        $this->handle = $handle;
        $this->binding = $binding;
    }

    /**
     * @param  callable(array<string, mixed>): array<string, mixed>  $handle
     */
    public static function for(string $uri, callable $handle, Binding $binding = Binding::Local): self
    {
        return new self($uri, $handle, $binding);
    }

    /** The namespace URI — the popcorn registry key handlers bind by (asset 06 §3). */
    public function name(): string
    {
        return $this->uri;
    }

    public function binding(): Binding
    {
        return $this->binding;
    }

    /**
     * @param  array<string, mixed>  $input  the {value, pointer, namespace, context} envelope
     * @return array<string, mixed>
     */
    public function invoke(array $input): array
    {
        return ($this->handle)($input);
    }
}
