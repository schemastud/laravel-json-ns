<?php

namespace Schemastud\JsonNs\Laravel;

use Rushing\Popcorn\Contracts\Invocable;
use Rushing\Popcorn\InvocableRegistry;
use Rushing\Popcorn\Registries\IsRegistry;
use Rushing\Popcorn\Registries\OnDuplicate;
use Rushing\Popcorn\Registries\Optionality;
use Rushing\Popcorn\Registries\RegistryArity;
use Schemastud\JsonNs\NamespaceUriKey;

/**
 * The URI-keyed namespace handlers — json-ns's own branch, and the estate's one FOREIGN-KEYED
 * registry.
 *
 * ## The root is how the index finds it, not how its entries are addressed
 *
 * Every entry here is keyed by a {@see NamespaceUriKey}, not a {@see \Rushing\Popcorn\Registries\Key},
 * because URI-is-identity is this package's whole thesis (asset 05 §5) and predates popcorn's
 * grammar. The kernel never stamps a foreign key — it cannot construct a consumer's key type — so
 * these entries are relative-forever and are NOT addressable through the global keyspace
 * (registry-kernel ticket 20 D3). A URI's `:` and `/` would fail `Key::SEGMENT_PATTERN` anyway.
 *
 * So `jsonns.namespaces` addresses the REGISTRY, reachable through {@see RegistryIndex::owner()},
 * and nothing under it is reachable through `pop()`. That is the designed shape and the same line
 * ticket 13 drew from the other end when `ExistsInRegistry` hard-refuses a foreign-keyed registry.
 *
 * ## Why it stops sharing the estate pool
 *
 * The old comment on the provider said sharing one `InvocableRegistry` let the namespace layer
 * register "alongside the grounding-kernel / composition-engine consumers". It did — and that was
 * the problem: three packages' capabilities in one keyspace, where a namespace URI sat beside
 * `music.render` with nothing but a name to tell them apart, and a `forget()` in one package's
 * teardown reached every other package's entries.
 */
#[IsRegistry(
    root: 'jsonns.namespaces',
    of: 'namespace handlers, keyed by namespace URI rather than by a dotted key',
    arity: RegistryArity::PickOne,
    entryType: Invocable::class,
    onDuplicate: OnDuplicate::Supersede,
    optionality: Optionality::Optional,
    note: 'Rebinding a URI replaces the prior handler — the documented last-registered-wins of the '
        .'dispatch socket (asset 06 §7), not an accident. Entries carry a foreign RegistryKey, so '
        .'they are addressable only through this registry, never through the global keyspace.',
)]
class NamespaceRegistry extends InvocableRegistry {}
