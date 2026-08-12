> You are in **schemastud/laravel-json-ns** — the Laravel binding for the `@namespace` grammar.

A URI-keyed dispatch socket over `schemastud/php-json-ns` + `rushing/php-popcorn`: bind your own
namespace URI to your own handler (registered as a popcorn `Invocable`), with
pick-one-by-URI (pinned > stem) resolution and a no-op-after-validation fallback.

## Vendored family-package conventions

Any repo that vendors another family repo's code (composer `vendor/<vendor>/<pkg>/`, npm
`node_modules/<vendor>/<pkg>/`) checks that vendored repo's own `AGENTS.md` for conventions it
ships with itself before editing through into it.
