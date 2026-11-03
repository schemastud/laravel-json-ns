<?php

use Rushing\Popcorn\Registries\Docs\RegistryDocsCheck;

/*
 * This package adopts the package-tier registry docs check over the whole of `src/`: its one declared
 * root, `jsonns.namespaces`, has its `docs/registries/jsonns.namespaces.mdx` page, and the page's
 * generated facts block is byte-identical to a fresh render of the `#[IsRegistry]` declaration. The
 * coverage is asserted too, so an inactive check cannot pass by reporting no failures.
 */
it('documents every declared registry root with its facts in step', function () {
    $report = RegistryDocsCheck::forPackage(dirname(__DIR__))->run();

    expect($report->failures())->toBe([], $report->summary())
        ->and(array_keys($report->coverage))->toBe(['jsonns.namespaces']);
});
