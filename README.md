# schemastud/laravel-json-ns

The **Laravel binding for the `@namespace` grammar** — a URI-keyed dispatch socket over
`schemastud/php-json-ns` + `rushing/php-popcorn`: bind your own namespace URI to your own handler,
registered as a popcorn `Invocable`, with pick-one-by-URI (pinned > stem) resolution and a
no-op-after-validation fallback.

```bash
composer require schemastud/laravel-json-ns
```

The socket, one file each under `src/`:

- `NamespaceRegistry` — where a URI is bound to a handler;
- `NamespaceHandler` — the contract your handler satisfies;
- `NamespaceDispatcher` — pick-one-by-URI resolution (pinned beats stem), then dispatch;
- `Envelope` — what a handler receives;
- `JsonNsServiceProvider` — the wiring.

The grammar itself (maps, scopes, overlays, CURIEs) lives in the framework-free
[`schemastud/php-json-ns`](../php-json-ns/); this package only owns the Laravel dispatch seam.
Start with [`AGENTS.md`](AGENTS.md). Tests: `composer test`.
