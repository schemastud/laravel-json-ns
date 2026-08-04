<?php

namespace Schemastud\JsonNs\Laravel;

use Rushing\Popcorn\InvocableRegistry;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

/**
 * The Laravel binding (asset 06 §8): where the popcorn coupling rides. It exposes the
 * URI-keyed dispatch socket — a {@see NamespaceDispatcher} over the shared
 * {@see InvocableRegistry} — so a host can bind its own namespace URI to its own handler.
 *
 * The framework-free grammar (`schemastud/php-json-ns`) stays pure; nothing here leaks into
 * it. This provider only wires the dispatch tier.
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
    }
}
