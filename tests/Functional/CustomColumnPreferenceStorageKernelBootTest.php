<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Functional;

use CoopTilleuls\Bundle\EAExtensionsBundle\CustomizableColumns\Entity\ColumnPreference;
use CoopTilleuls\Bundle\EAExtensionsBundle\CustomizableColumns\Repository\ColumnPreferenceRepository;
use CoopTilleuls\Bundle\EAExtensionsBundle\CustomizableColumns\Storage\ColumnPreferenceStorageInterface;
use CoopTilleuls\Bundle\EAExtensionsBundle\CustomizableColumns\Storage\SecurityUserColumnPreferenceStorage;
use CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Fixtures\CustomColumnPreferenceStorageKernel;
use CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Fixtures\InMemoryColumnPreferenceStorage;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class CustomColumnPreferenceStorageKernelBootTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return CustomColumnPreferenceStorageKernel::class;
    }

    public function testColumnPreferenceEntityIsNotMappedWhenACustomStorageIsConfigured(): void
    {
        self::bootKernel(['debug' => false]);

        /** @var ManagerRegistry $registry */
        $registry = self::getContainer()->get('doctrine');
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $registry->getManager();

        // Not isTransient(): with doctrine-bundle's entity auto-discovery (3.4+), the default
        // attribute driver reports any #[ORM\Entity] class as non-transient, even one it never
        // registers. What matters is that the entity is not managed, so it gets no table.
        $managedClasses = array_map(
            static fn (ClassMetadata $metadata): string => $metadata->getName(),
            $entityManager->getMetadataFactory()->getAllMetadata(),
        );

        self::assertNotContains(ColumnPreference::class, $managedClasses);
    }

    public function testConfiguredStorageReplacesTheDefaultOne(): void
    {
        self::bootKernel(['debug' => false]);
        $container = self::getContainer();

        self::assertInstanceOf(InMemoryColumnPreferenceStorage::class, $container->get(ColumnPreferenceStorageInterface::class));
        self::assertFalse($container->has(SecurityUserColumnPreferenceStorage::class));
        self::assertFalse($container->has(ColumnPreferenceRepository::class));
    }
}
