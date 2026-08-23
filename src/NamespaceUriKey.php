<?php

namespace Schemastud\JsonNs\Laravel;

use Rushing\Popcorn\Registries\RegistryKey;
use Schemastud\JsonNs\NamespaceUri;

/**
 * A namespace URI as a popcorn {@see RegistryKey} — this package's own key type, which is the one
 * thing registry-kernel ticket 05's amendment sanctions and deliberately makes rare.
 *
 * ## Why json-ns earns one and almost nothing else does
 *
 * Because URI-is-identity is this package's whole thesis and it PREDATES popcorn: a CURIE key and an
 * `@namespaced` object that resolve to the same URI must hit the same registration (asset 05 §5). A
 * dotted key with the URI demoted to an alias would relocate that identity into a lookup table — which
 * is precisely the drift the doctrine exists to forbid.
 *
 * `RegistryKey` is a SEAM, not a grammar: the kernel COMPARES and JOINS segments and never parses one,
 * so a key type is CONSTRUCTED, never parsed, and popcorn's `[a-z0-9-]` charset governs only
 * {@see \Rushing\Popcorn\Registries\Key} — the canonical implementation for keys written as string
 * literals. A URI's `:` and `/` would fail that charset outright, and it does not have to survive it.
 *
 * ## The pin is a CHILD of its stem
 *
 * `https://…/grounding/2` segments as `['https://…/grounding', '2']`, which makes the version pin
 * structurally a child of the version-free stem. That is not decoration: it is what expresses the
 * dispatcher's pinned-then-stem fallback (asset 05 §8, asset 06 §7.1) as ordinary tree structure
 * instead of string surgery, and what lets `children()` enumerate the versions bound under a stem.
 *
 * ## The consequence to design around
 *
 * A foreign key is never stamped with a registry root, because the kernel cannot construct a consumer's
 * key type. So these entries are **not addressable through the global keyspace** — reachable through
 * this package's own registry and through {@see NamespaceDispatcher}, never through `Popcorn::pop()`
 * (registry-kernel ticket 20 D3). That is the trade URI-is-identity buys, taken deliberately.
 */
class NamespaceUriKey implements RegistryKey
{
    /** @param  list<string>  $segments */
    private function __construct(private array $segments, private string $uri) {}

    public static function of(string|NamespaceUri $uri): self
    {
        $parsed = $uri instanceof NamespaceUri ? $uri : NamespaceUri::from((string) $uri);

        return new self(
            $parsed->isPinned() ? [$parsed->stem(), (string) $parsed->version()] : [(string) $parsed],
            (string) $parsed,
        );
    }

    /** @return list<string> */
    public function segments(): array
    {
        return $this->segments;
    }

    /**
     * Equality is defined on SEGMENTS, never on the source string — the property that makes two
     * spellings of one identity the same key and two keyspaces that happen to render alike distinct.
     */
    public function equals(RegistryKey $other): bool
    {
        return $this->segments === $other->segments();
    }

    /** The URI itself. A rendering for humans and transports; never the identity. */
    public function __toString(): string
    {
        return $this->uri;
    }
}
