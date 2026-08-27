<?php

namespace Schemastud\JsonNs\Laravel\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Rushing\Popcorn\Laravel\PopcornServiceProvider;
use Schemastud\DataSchemas\LaravelDataSchemasServiceProvider;
use Schemastud\JsonNs\Laravel\JsonNsServiceProvider;

abstract class TestCase extends Orchestra
{
    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            // laravel-popcorn binds RegistryIndex as a SINGLETON. Without it the index is
            // auto-resolvable but unshared, so an owner's describe() lands on a throwaway and
            // silently does nothing — the failure mode that makes index membership a function
            // of host composition (registry-kernel ticket 04 D1).
            PopcornServiceProvider::class,

            // Same trap, different registry. `JsonNsServiceProvider` binds `VocabularyRegistry`
            // BACKED by the host's `SchemaRegistry` when one is bound and standalone in-memory
            // otherwise — and testbench does not auto-discover, so without this provider nothing
            // ever binds `SchemaRegistry` here and the backed branch is unreachable except by a
            // test hand-binding a `FilesystemSchemaRegistry` itself. That is a harness whose
            // DEFAULT is the branch no host with `schemastud/laravel-data-schemas` installed will
            // ever take: green suite, wrong tier. Registering it makes the harness mirror the host
            // this package is written for.
            LaravelDataSchemasServiceProvider::class,

            JsonNsServiceProvider::class,
        ];
    }
}
