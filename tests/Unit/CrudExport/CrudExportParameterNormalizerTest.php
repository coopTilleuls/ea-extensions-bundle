<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Unit\CrudExport;

use CoopTilleuls\Bundle\EAExtensionsBundle\CrudExport\Normalizer\CrudExportParameterNormalizer;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\ClassMetadataFactory;
use PHPUnit\Framework\TestCase;

final class CompositePkEntity
{
    public function __construct(public int $left, public int $right)
    {
    }
}

final class CrudExportParameterNormalizerTest extends TestCase
{
    /**
     * Regression test: cogitiel's original normalizer only kept the FIRST identifier
     * value (`array_values($identifier)[0]`), silently corrupting any composite-PK entity
     * used as a query parameter. The bundle's port must round-trip the full identifier map.
     */
    public function testNormalizeAndDenormalizeRoundTripsCompositeIdentifier(): void
    {
        $entity = new CompositePkEntity(3, 7);
        $identifier = ['left' => 3, 'right' => 7];

        $classMetadata = $this->createMock(ClassMetadata::class);
        $classMetadata->method('getIdentifierValues')->with($entity)->willReturn($identifier);

        $metadataFactory = $this->createMock(ClassMetadataFactory::class);
        $metadataFactory->method('hasMetadataFor')->with(CompositePkEntity::class)->willReturn(true);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getMetadataFactory')->willReturn($metadataFactory);
        $em->method('getClassMetadata')->with(CompositePkEntity::class)->willReturn($classMetadata);

        $normalizer = new CrudExportParameterNormalizer($em);

        $normalized = $normalizer->normalizeParameterValue($entity);

        self::assertSame([
            '_entity' => true,
            'class' => CompositePkEntity::class,
            'id' => $identifier,
        ], $normalized);

        $em->expects(self::once())
            ->method('find')
            ->with(CompositePkEntity::class, $identifier)
            ->willReturn($entity);

        self::assertSame($entity, $normalizer->denormalizeParameterValue($normalized));
    }

    public function testNormalizeArrayRecurses(): void
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $normalizer = new CrudExportParameterNormalizer($em);

        self::assertSame(['a' => 1, 'b' => 2], $normalizer->normalizeParameterValue(['a' => 1, 'b' => 2]));
    }

    public function testNormalizeScalarIsUnchanged(): void
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $normalizer = new CrudExportParameterNormalizer($em);

        self::assertSame('hello', $normalizer->normalizeParameterValue('hello'));
    }
}
