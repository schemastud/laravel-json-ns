<?php

namespace Schemastud\JsonNs\Laravel;

use Rushing\Popcorn\InvocableRegistry;
use Schemastud\DataSchemas\Contracts\EnumeratesVersions;
use Schemastud\DataSchemas\Contracts\SchemaRegistry;
use Schemastud\JsonNs\NamespaceUri;
use Schemastud\JsonNs\Vocab\VocabularyRegistry;
use Schemastud\JsonNs\Vocab\VocabularyValidator;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

/**
 * The Laravel binding (asset 06 §8): where the popcorn coupling rides. It exposes the
 * URI-keyed dispatch socket — a {@see NamespaceDispatcher} over the shared
 * {@see InvocableRegistry} — so a host can bind its own namespace URI to its own handler.
 *
 * The framework-free grammar (`schemastud/php-json-ns`) stays pure; nothing here leaks into
 * it. This provider wires the dispatch tier AND (JN-16 / ADR-0192 §6) hosts the validation
 * half: {@see VocabularyRegistry} is bound backed by the host's `SchemaRegistry` when one is
 * bound — vocabularies resolve as registry artifacts (exact `$id`, then latest-of-stem via
 * version enumeration) — and standalone in-memory otherwise. A host that declares namespaces
 * whose absent artifacts must FAIL loudly lists their URIs in
 * `config('json-ns.required_namespaces')` (see `VocabularyArtifactMissing`).
 */
class JsonNsServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package->name('laravel-json-ns');
    }

    public function packageRegistered(): void
    {
        // Share the popcorn registry so the namespace layer registers alongside the
        // grounding-kernel / composition-engine consumers into ONE InvocableRegistry.
        $this->app->singleton(InvocableRegistry::class);

        $this->app->singleton(NamespaceDispatcher::class, function ($app): NamespaceDispatcher {
            return new NamespaceDispatcher($app->make(InvocableRegistry::class));
        });

        // The vocabulary store port finally gets its host (JN-16): backed by the beam-style
        // SchemaRegistry seam when the host binds one. The lookup re-makes the registry per
        // call — SchemaRegistry is deliberately bound not-shared so a multi-tenant host's DB
        // tier follows the active tenant connection.
        $this->app->singleton(VocabularyRegistry::class, function ($app): VocabularyRegistry {
            if (! interface_exists(SchemaRegistry::class) || ! $app->bound(SchemaRegistry::class)) {
                return VocabularyRegistry::make();
            }

            return VocabularyRegistry::backedBy(
                function (string $uri) use ($app): object|bool|null {
                    $store = $app->make(SchemaRegistry::class);

                    $artifact = $store->get($uri);

                    // A version-free stem has no exact `$id` row; resolve it to the stem's
                    // LATEST version when the store can enumerate (the lifecycle pattern —
                    // fleet artifacts carry absolute versioned URI $ids).
                    if ($artifact === null && $store instanceof EnumeratesVersions && NamespaceUri::from($uri)->isUnpinned()) {
                        $versions = $store->versionsFor($uri);

                        if ($versions !== []) {
                            $artifact = $store->get($uri.'/'.max($versions));
                        }
                    }

                    if ($artifact === null) {
                        return null;
                    }

                    // opis validates stdClass/scalars, not assoc arrays.
                    return json_decode((string) json_encode($artifact), false);
                },
                declared: array_map('strval', (array) $app['config']->get('json-ns.required_namespaces', [])),
            );
        });

        $this->app->bind(VocabularyValidator::class, function ($app): VocabularyValidator {
            return new VocabularyValidator($app->make(VocabularyRegistry::class));
        });
    }
}
