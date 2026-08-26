<?php

namespace Schemastud\JsonNs\Laravel;

use Rushing\Popcorn\Binding;
use Rushing\Popcorn\Contracts\Invocable;
use Schemastud\JsonNs\NamespaceUri;
use Schemastud\JsonNs\NamespaceUriKey;

/**
 * The public binding API for the URI-keyed dispatch socket (asset 06). A host uses it to:
 *
 *   1. BIND a namespace URI to its own handler — {@see bind()} wraps a plain callback as a
 *      popcorn {@see NamespaceHandler} (whose `name()` = the URI) and registers it into
 *      {@see NamespaceRegistry}, json-ns's own branch of the keyspace. No new port; the
 *      namespace layer is the registry seam's third consumer (asset 06 §3).
 *
 *   2. DISPATCH a resolved namespace URI to whatever handler is bound to it — {@see dispatch()}
 *      applies **pick-one by URI** with the **no-op-after-validation** fallback (§7).
 *
 * Resolution semantics (asset 06 §7 + asset 05 §8):
 *
 *   - **Pick-one, pinned > stem.** For a resolved URI, an exact match wins. If the URI is
 *     *pinned* (`.../grounding/2`) and no handler is registered under that exact pin, dispatch
 *     falls back to a handler registered on the version-free *stem* (`.../grounding`) — the pin
 *     out-specifies the stem, but the stem-bound handler fires across all versions. If the URI
 *     is *unpinned* (a stem), only an exact stem match counts (a stem must not silently pick an
 *     arbitrary pinned version).
 *
 *   - **Missing handler = no-op AFTER validation.** When no handler is bound to the URI (nor to
 *     its stem), dispatch is a no-op — it returns the node's value unchanged wrapped as
 *     `['value' => <value>]`, NOT an error. Validation already happened upstream (php-json-ns);
 *     dispatch simply has nothing to do. This is what makes validation-only free (§7.2).
 *
 *   - **Last-registered-wins on rebind** — inherited from popcorn's name-keyed `register()`.
 *
 * URI-is-identity (asset 05 §5) means a CURIE key and an `@namespaced` object that resolve to
 * the SAME URI hit the SAME registration — the host resolves both to a URI via the framework-free
 * `Schemastud\JsonNs\Resolver`, then dispatches here.
 */
class NamespaceDispatcher
{
    private NamespaceRegistry $registry;

    public function __construct(NamespaceRegistry $registry)
    {
        $this->registry = $registry;
    }

    /**
     * Bind a namespace URI to a host handler. The callback receives the standard envelope
     * (§6, {@see Envelope}) and returns an array. Rebinding the same URI replaces the prior
     * handler (popcorn `register` is name-keyed).
     *
     * @param  string|NamespaceUri  $uri  the namespace URI to bind (stem to fire across all
     *                                    versions, pinned to fire only for that version)
     * @param  callable(array<string, mixed>): array<string, mixed>  $handle  the host plug
     */
    public function bind(string|NamespaceUri $uri, callable $handle, Binding $binding = Binding::Local): static
    {
        $this->registry->register(new NamespaceHandler((string) $uri, $handle, $binding));

        return $this;
    }

    /** Register a pre-built {@see Invocable} whose `name()` is already a namespace URI. */
    public function register(Invocable $handler): static
    {
        $this->registry->register($handler);

        return $this;
    }

    /** True when SOME handler would fire for this URI (exact, or a stem fallback for a pin). */
    public function bound(string|NamespaceUri $uri): bool
    {
        return $this->handlerFor(NamespaceUri::from((string) $uri)) !== null;
    }

    /**
     * Dispatch a resolved namespace URI with an already-built envelope. Applies pick-one
     * (pinned > stem) then the no-op-after-validation fallback.
     *
     * @return array<string, mixed>
     */
    public function dispatchEnvelope(Envelope $envelope): array
    {
        return $this->dispatch($envelope->namespace(), $envelope->toArray());
    }

    /**
     * Dispatch a resolved namespace URI with the raw `{value, pointer, namespace, context}`
     * input. Prefer {@see dispatchEnvelope()} for the typed builder.
     *
     * @param  array{value?: mixed, pointer?: string, namespace?: string, context?: array<string, mixed>}  $input
     * @return array<string, mixed>
     */
    public function dispatch(string|NamespaceUri $uri, array $input): array
    {
        $handler = $this->handlerFor(NamespaceUri::from((string) $uri));

        // Missing handler = no-op after validation ("OR NOT", §7.2): leave the node untouched.
        if ($handler === null) {
            return ['value' => $input['value'] ?? null];
        }

        return $handler->invoke($input);
    }

    /**
     * Pick the handler that answers a resolved URI, or null when none does.
     *
     * Pick-one by URI, pinned > stem (asset 05 §8, asset 06 §7.1):
     *   - an EXACT registration on the requested URI always wins;
     *   - failing that, if the requested URI is PINNED, a registration on its version-free
     *     STEM answers it (the stem-bound handler fires across all versions);
     *   - a requested STEM only ever matches an exact stem registration.
     *
     * Addressed by {@see NamespaceUriKey}, never by the rendered URI string: since registry-kernel
     * ticket 30 the registry is keyed by `RegistryKey`, and popcorn's canonical `Key` would reject a
     * URI's `:` and `/` outright. Equality is on segments, so the key object is the only thing that
     * can express "this URI" to the registry — the rendering is for humans.
     *
     * **Probed with `has()` rather than `tryResolve()`, and that is load-bearing.** A pin segments as a
     * CHILD of its stem, so to the kernel a stem request with pins registered under it is a request for
     * a BRANCH — which `resolve()` reports as ambiguous rather than absent, and `tryResolve()` rethrows
     * (a question with several answers is not a miss). That is the kernel being right: it cannot know
     * this package ranks a pin over its stem. `has()` is exact-only, so probing with it expresses §7.1's
     * "a requested stem only ever matches an exact stem registration" directly, and the ranking stays
     * where it belongs — here, in the consumer that owns the rule.
     *
     * **Deliberately NOT re-pointed at `Nested::nodeAt()`**, the three-state branch probe this workaround
     * helped motivate (registry-kernel ticket 46). That verb exists so a caller can tell "a branch lives
     * here" from "no such key" — and §7.1 is the one rule that does not want to tell them apart. A stem
     * with pins below it and a stem nobody has ever mentioned are the same answer here: no exact
     * registration, fall through. `nodeAt($k) === RegistryNode::Entry` would be a longer spelling of
     * `has($k)`, so the verb landing changes nothing for this dispatcher — which is the check that it
     * fits, not that it misses.
     */
    private function handlerFor(NamespaceUri $requested): ?Invocable
    {
        $exact = NamespaceUriKey::of($requested);

        if ($this->registry->has($exact)) {
            return $this->registry->resolve($exact);
        }

        if ($requested->isPinned()) {
            $stem = NamespaceUriKey::of($requested->stem());

            if ($this->registry->has($stem)) {
                return $this->registry->resolve($stem);
            }
        }

        return null;
    }
}
