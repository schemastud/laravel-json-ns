<?php

namespace Schemastud\JsonNs\Laravel\Tests;

use Rushing\Popcorn\Binding;
use Rushing\Popcorn\Contracts\Invocable;
use Rushing\Popcorn\Registries\HasRegistryKey;
use Rushing\Popcorn\Registries\RegistryKey;
use Schemastud\JsonNs\NamespaceUriKey;

/**
 * A test double that records every envelope it was invoked with and echoes it back tagged, so
 * a test can assert (a) WHICH fake fired (pick-one) and (b) that the {value, pointer,
 * namespace, context} envelope passed through unchanged.
 */
class FakeHandler implements HasRegistryKey, Invocable
{
    private string $uri;

    private string $tag;

    /** @var array<int, array<string, mixed>> */
    public array $calls = [];

    public function __construct(string $uri, string $tag)
    {
        $this->uri = $uri;
        $this->tag = $tag;
    }

    /** Keyed by URI, exactly as {@see \Schemastud\JsonNs\Laravel\NamespaceHandler} is. */
    public function registryKey(): RegistryKey
    {
        return NamespaceUriKey::of($this->uri);
    }

    public function name(): string
    {
        return $this->uri;
    }

    public function binding(): Binding
    {
        return Binding::Local;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function invoke(array $input): array
    {
        $this->calls[] = $input;

        return ['tag' => $this->tag, 'echo' => $input];
    }
}
