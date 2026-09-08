<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Functional;

use CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Fixtures\DisabledNotificationsKernel;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaValidator;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DisabledNotificationsKernelBootTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return DisabledNotificationsKernel::class;
    }

    public function testDoctrineMappingValidatesWithoutResolveTargetEntitiesWhenNotificationsAreDisabled(): void
    {
        self::bootKernel(['debug' => false]);

        /** @var ManagerRegistry $registry */
        $registry = self::getContainer()->get('doctrine');
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $registry->getManager();

        // Loading metadata alone doesn't trip Doctrine's resolve_target_entities requirement for
        // interface-typed associations — SchemaValidator::validateMapping() is what actually
        // resolves them (as does "doctrine:schema:validate", or any real schema-generation
        // command), which is what surfaced this for a host app disabling the feature.
        self::assertSame([], new SchemaValidator($entityManager)->validateMapping());
    }
}
