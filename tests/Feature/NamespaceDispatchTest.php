<?php

use Rushing\Popcorn\InvocableRegistry;
use Schemastud\JsonNs\Laravel\Envelope;
use Schemastud\JsonNs\Laravel\NamespaceDispatcher;
use Schemastud\JsonNs\Laravel\Tests\FakeHandler;
use Schemastud\JsonNs\NamespaceUri;
use Schemastud\JsonNs\Resolver;

const STEM = 'https://schemas.splicewire.app/splice/grounding';
const PINNED = 'https://schemas.splicewire.app/splice/grounding/2';

/**
 * The test seam (asset 06): register a FAKE Invocable by URI into the shared registry, then
 * assert dispatch through the InvocableRegistry::invoke(uri, input) socket.
 */
beforeEach(function () {
    $this->registry = app(InvocableRegistry::class);
    $this->dispatcher = app(NamespaceDispatcher::class);
});

// (a) pick-one: a PINNED URI wins over the version-free STEM when both are registered.
it('picks the pinned handler over the stem when both are registered', function () {
    $stem = new FakeHandler(STEM, 'stem');
    $pinned = new FakeHandler(PINNED, 'pinned');
    $this->registry->register($stem)->register($pinned);

    $out = $this->dispatcher->dispatch(PINNED, envelopeArray(PINNED));

    expect($out['tag'])->toBe('pinned')
        ->and($pinned->calls)->toHaveCount(1)
        ->and($stem->calls)->toHaveCount(0);
});

// pick-one, fallback arm: a pinned request with NO exact pin falls back to the stem handler.
it('falls back to the stem handler for a pinned URI with no exact pin registered', function () {
    $stem = new FakeHandler(STEM, 'stem');
    $this->registry->register($stem);

    $out = $this->dispatcher->dispatch(PINNED, envelopeArray(PINNED));

    expect($out['tag'])->toBe('stem')
        ->and($stem->calls)->toHaveCount(1);
});

// a bare STEM request must NOT pick an arbitrary pinned registration.
it('does not resolve a stem request to a pinned handler', function () {
    $this->registry->register(new FakeHandler(PINNED, 'pinned'));

    expect($this->dispatcher->bound(STEM))->toBeFalse();

    $out = $this->dispatcher->dispatch(STEM, envelopeArray(STEM));

    expect($out)->toBe(['value' => ['sources' => ['ctx://profile']]]);
});

// (b) no-op when missing: no handler bound → return value unchanged, NOT an error.
it('is a no-op after validation when no handler is registered', function () {
    expect($this->dispatcher->bound(STEM))->toBeFalse();

    $out = $this->dispatcher->dispatch(STEM, envelopeArray(STEM));

    expect($out)->toBe(['value' => ['sources' => ['ctx://profile']]]);
});

// (c) a CURIE key and an @namespaced object resolve to the SAME URI → hit the SAME handler.
it('routes a CURIE key and an @namespaced object to the same registered handler', function () {
    $handler = new FakeHandler(STEM, 'grounding');
    $this->registry->register($handler);

    // Key-form: the CURIE key "splice:grounding" resolves via the @namespace map.
    $keyDoc = [
        '@namespace' => ['splice' => STEM],
        'splice:grounding' => ['sources' => ['ctx://profile']],
    ];
    $keyResolver = Resolver::for($keyDoc);
    $keyUri = $keyResolver->resolveCurie('splice:grounding');

    // Object-form: an @namespaced overlay re-scopes an unprefixed subtree to the SAME URI.
    $objDoc = [
        '@namespace' => ['splice' => STEM],
        'config' => [
            'grounding' => [
                '@namespaced' => 'splice',
                'sources' => ['ctx://profile'],
            ],
        ],
    ];
    $objResolver = Resolver::for($objDoc);
    $objScope = $objResolver->scopeAt('/config/grounding');
    $objUri = $objScope->uri();

    // Both attachment points resolve to the same URI (asset 05 §5 — URI is identity).
    expect((string) $keyUri)->toBe(STEM)
        ->and((string) $objUri)->toBe(STEM)
        ->and($keyUri->equals($objUri))->toBeTrue();

    // ...and therefore dispatch to the same registered handler.
    $this->dispatcher->dispatch($keyUri, envelopeArray((string) $keyUri));
    $this->dispatcher->dispatch($objUri, envelopeArray((string) $objUri));

    expect($handler->calls)->toHaveCount(2);
});

// (d) envelope shape {value, pointer, namespace, context} passes through unchanged.
it('passes the {value, pointer, namespace, context} envelope through to the handler', function () {
    $handler = new FakeHandler(STEM, 'grounding');
    $this->registry->register($handler);

    $envelope = Envelope::make(
        value: ['sources' => ['ctx://profile'], 'max_depth' => 3],
        pointer: '/config/grounding',
        namespace: NamespaceUri::from(STEM),
        context: ['profile' => 'standard', 'tenant' => 'acme', 'caller' => 'cli'],
    );

    $this->dispatcher->dispatchEnvelope($envelope);

    expect($handler->calls[0])->toBe([
        'value' => ['sources' => ['ctx://profile'], 'max_depth' => 3],
        'pointer' => '/config/grounding',
        'namespace' => STEM,
        'context' => ['profile' => 'standard', 'tenant' => 'acme', 'caller' => 'cli'],
    ]);
});

/**
 * @return array{value: mixed, pointer: string, namespace: string, context: array<string, mixed>}
 */
function envelopeArray(string $uri): array
{
    return [
        'value' => ['sources' => ['ctx://profile']],
        'pointer' => '/config/grounding',
        'namespace' => $uri,
        'context' => [],
    ];
}
