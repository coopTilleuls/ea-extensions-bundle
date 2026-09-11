<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\CrudExport\Normalizer;

use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;

/**
 * (De)normalizes Doctrine query parameters — including entities — so a Doctrine Query's
 * DQL + parameters can cross the Messenger async boundary as plain serializable data and
 * be rebuilt identically in the message handler.
 */
final readonly class CrudExportParameterNormalizer
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function normalizeParameterValue(mixed $value): mixed
    {
        if (is_object($value) && $this->em->getMetadataFactory()->hasMetadataFor($value::class)) {
            $classMetadata = $this->em->getClassMetadata($value::class);

            return [
                '_entity' => true,
                'class' => $value::class,
                // Full identifier map (not just its first value) so entities with a composite
                // primary key round-trip correctly.
                'id' => $classMetadata->getIdentifierValues($value),
            ];
        }

        if (is_array($value)) {
            return array_map(fn ($v): mixed => $this->normalizeParameterValue($v), $value);
        }

        if ($value instanceof Collection) {
            return array_map(fn ($v): mixed => $this->normalizeParameterValue($v), $value->toArray());
        }

        return $value;
    }

    public function denormalizeParameterValue(mixed $value): mixed
    {
        if (is_array($value) && isset($value['_entity'], $value['class'], $value['id'])) {
            /** @var class-string $class */
            $class = $value['class'];

            return $this->em->find($class, $value['id']);
        }

        if (is_array($value)) {
            return array_map(fn ($v): mixed => $this->denormalizeParameterValue($v), $value);
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $parameters
     *
     * @return Query<null, mixed>
     */
    public function rebuildQuery(string $dql, array $parameters = []): Query
    {
        $query = $this->em->createQuery($dql);

        foreach ($parameters as $name => $value) {
            $query->setParameter($name, $this->denormalizeParameterValue($value));
        }

        return $query;
    }
}
