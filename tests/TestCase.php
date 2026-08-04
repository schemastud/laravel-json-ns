<?php

namespace Schemastud\JsonNs\Laravel\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Schemastud\JsonNs\Laravel\JsonNsServiceProvider;

abstract class TestCase extends Orchestra
{
    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            JsonNsServiceProvider::class,
        ];
    }
}
