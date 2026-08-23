<?php

namespace Schemastud\JsonNs\Laravel\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Rushing\Popcorn\Laravel\PopcornServiceProvider;
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
            JsonNsServiceProvider::class,
        ];
    }
}
