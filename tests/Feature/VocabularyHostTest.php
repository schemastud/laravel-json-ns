<?php

use Schemastud\DataSchemas\Contracts\SchemaRegistry;
use Schemastud\DataSchemas\Lifecycle\FilesystemSchemaRegistry;
use Schemastud\JsonNs\Exceptions\VocabularyArtifactMissing;
use Schemastud\JsonNs\NamespaceUri;
use Schemastud\JsonNs\Vocab\VocabularyRegistry;
use Schemastud\JsonNs\Vocab\VocabularyValidator;

/*
 * JN-16 (ADR-0192 §6) — the VocabularyRegistry port finally gets its host: this provider binds
 * it BACKED by the host's SchemaRegistry when one is bound (vocabularies are registry-resolved
 * artifacts: exact $id, then latest-of-stem via version enumeration), standalone otherwise. A
 * namespace listed in config('json-ns.required_namespaces') whose artifact is absent is a hard
 * VocabularyArtifactMissing, never a permissive pass.
 */

const VOCAB_STEM = 'https://schemas.splicewire.app/splice/vocabulary';

function fleetDir(): string
{
    $dir = sys_get_temp_dir().'/jn16-fleet-'.getmypid();
    @mkdir($dir, 0775, true);

    return $dir;
}

function publishVocabArtifact(string $dir): void
{
    (new FilesystemSchemaRegistry($dir))->register([
        '$id' => VOCAB_STEM.'/1',
        'type' => 'object',
        'properties' => [
            'sources' => ['type' => 'array', 'items' => ['type' => 'string'], 'minItems' => 1],
        ],
        'required' => ['sources'],
    ]);
}

afterEach(function () {
    foreach (glob(fleetDir().'/*') ?: [] as $f) {
        @unlink($f);
    }
});

it('binds a standalone in-memory registry when the host has no SchemaRegistry', function () {
    expect(app()->bound(SchemaRegistry::class))->toBeFalse()
        ->and(app(VocabularyRegistry::class))->toBeInstanceOf(VocabularyRegistry::class)
        ->and(app(VocabularyRegistry::class)->has(NamespaceUri::from(VOCAB_STEM)))->toBeFalse();
});

it('resolves vocabularies through a bound SchemaRegistry — exact $id and latest-of-stem', function () {
    $dir = fleetDir();
    publishVocabArtifact($dir);

    app()->bind(SchemaRegistry::class, fn () => new FilesystemSchemaRegistry($dir));
    app()->forgetInstance(VocabularyRegistry::class);

    $registry = app(VocabularyRegistry::class);

    // Exact pinned $id…
    expect($registry->schemaFor(NamespaceUri::from(VOCAB_STEM.'/1')))->not->toBeNull()
        // …and the version-free stem resolves to the stem's LATEST via version enumeration.
        ->and($registry->schemaFor(NamespaceUri::from(VOCAB_STEM)))->not->toBeNull();

    // The whole validator path enforces a registry-resolved vocabulary.
    $validator = app(VocabularyValidator::class);

    $doc = fn (array $node) => [
        '@namespace' => ['sv' => VOCAB_STEM],
        'sv:thing' => $node,
    ];

    expect($validator->passes($doc(['sources' => ['ctx://a']])))->toBeTrue()
        ->and($validator->passes($doc(['sources' => []])))->toBeFalse();
});

it('fails loudly for a declared namespace with no published artifact', function () {
    config()->set('json-ns.required_namespaces', ['https://schemas.example.com/declared/ns']);

    app()->bind(SchemaRegistry::class, fn () => new FilesystemSchemaRegistry(fleetDir()));
    app()->forgetInstance(VocabularyRegistry::class);

    $validator = app(VocabularyValidator::class);

    $doc = [
        '@namespace' => ['dn' => 'https://schemas.example.com/declared/ns'],
        'dn:thing' => ['whatever' => true],
    ];

    expect(fn () => $validator->validate($doc))->toThrow(VocabularyArtifactMissing::class);

    // An UNDECLARED unknown namespace keeps the permissive pass (unknown ≠ invalid).
    config()->set('json-ns.required_namespaces', []);
    app()->forgetInstance(VocabularyRegistry::class);

    expect(app(VocabularyValidator::class)->passes($doc))->toBeTrue();
});
