<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Functional;

use CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Fixtures\Entity\FixtureEntity;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @phpstan-require-extends AbstractFunctionalTestCase
 */
trait PaginationTestTrait
{
    /**
     * @param array<string, string> $extraParams
     */
    protected function autocompleteUrl(string $crudControllerFqcn, array $extraParams = []): string
    {
        $url = $this->crudUrl($crudControllerFqcn, 'autocomplete', $extraParams);

        return $url.(str_contains($url, '?') ? '&' : '?').http_build_query([
            'autocompleteContext' => ['originatingPage' => 'index', 'propertyName' => 'title'],
        ]);
    }

    /**
     * @return list<mixed>
     */
    protected function autocompleteResults(): array
    {
        $payload = json_decode((string) $this->client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($payload);
        self::assertIsArray($payload['results'] ?? null);

        return array_values($payload['results']);
    }

    protected function persistFixtureEntities(int $count): void
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        for ($i = 0; $i < $count; ++$i) {
            $entity = new FixtureEntity();
            $entity->setTitle('Title '.$i);
            $entity->setDescription('Description');
            $entity->setNote('Note');
            $entityManager->persist($entity);
        }

        $entityManager->flush();
    }
}
