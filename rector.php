<?php

declare(strict_types=1);

use Rector\Caching\ValueObject\Storage\FileCacheStorage;
use Rector\CodeQuality\Rector\BooleanAnd\RepeatedAndNotEqualToNotInArrayRector;
use Rector\CodeQuality\Rector\BooleanOr\RepeatedOrEqualToInArrayRector;
use Rector\Config\RectorConfig;
use Rector\Symfony\Symfony72\Rector\StmtsAwareInterface\PushRequestToRequestStackConstructorRector;
use Rector\Symfony\Symfony73\Rector\Class_\ConstraintOptionsToNamedArgumentsRector;
use Rector\Symfony\Symfony73\Rector\Class_\GetFiltersAndFunctionsToAsTwigAttributeRector;
use Rector\TypeDeclaration\Rector\ClassMethod\NarrowObjectReturnTypeRector;
use Rector\TypeDeclaration\Rector\ClassMethod\ParamTypeByMethodCallTypeRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/src',
        __DIR__.'/tests',
    ])
    ->withCache(cacheDirectory: './tmp/rector', cacheClass: FileCacheStorage::class)
    ->withParallel(240, 8, 8)
    ->withComposerBased(twig: false, doctrine: false, phpunit: true, symfony: true)
    ->withImportNames(importShortClasses: false)
    ->withPreparedSets(
        deadCode: true,
        typeDeclarations: true,
    )
    ->withSkip([
        ConstraintOptionsToNamedArgumentsRector::class,
        PushRequestToRequestStackConstructorRector::class,
        ParamTypeByMethodCallTypeRector::class,
        NarrowObjectReturnTypeRector::class,
        RepeatedAndNotEqualToNotInArrayRector::class,
        RepeatedOrEqualToInArrayRector::class,
        // #[AsTwigFunction] needs a newer twig/twig-bridge than this bundle's declared
        // "lowest" support floor — applying it makes the function silently unregistered
        // under a lowest-deps install (confirmed: 500 "Unknown function" at runtime).
        GetFiltersAndFunctionsToAsTwigAttributeRector::class,
    ])
    ->withCodeQualityLevel(15)
;
