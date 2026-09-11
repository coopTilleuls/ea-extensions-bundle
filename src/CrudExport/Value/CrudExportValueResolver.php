<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\CrudExport\Value;

use CoopTilleuls\Bundle\EAExtensionsBundle\CrudExport\CrudExportColumn;
use CoopTilleuls\Bundle\EAExtensionsBundle\CrudExport\CrudExportFieldResolverInterface;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;
use Symfony\Contracts\Service\ServiceProviderInterface;
use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class CrudExportValueResolver
{
    /**
     * @param ServiceProviderInterface<CrudExportFieldResolverInterface> $resolverLocator
     */
    public function __construct(
        #[AutowireLocator(CrudExportFieldResolverInterface::class)]
        private ServiceProviderInterface $resolverLocator,
        private TranslatorInterface $translator,
    ) {
    }

    public function resolveHeader(CrudExportColumn $column): string
    {
        $label = $column->label;
        if ($label instanceof TranslatableInterface) {
            $label = $label->trans($this->translator);
        } elseif (is_string($label)) {
            $label = $this->translator->trans($label);
        }

        return (string) ($label ?: $column->propertyName);
    }

    public function resolveValue(object $entity, CrudExportColumn $column): mixed
    {
        if (null !== $column->resolverFqcn) {
            /** @var CrudExportFieldResolverInterface $resolver */
            $resolver = $this->resolverLocator->get($column->resolverFqcn);

            return $resolver($entity);
        }

        $propertyParts = explode('.', $column->propertyName);

        if (count($propertyParts) > 1) {
            $entity = $this->resolveLastRelation($entity, $propertyParts);
            if (null === $entity) {
                return null;
            }
        }

        return $this->formatValue($this->getValueFromPropertyName($entity, $propertyParts[array_key_last($propertyParts)]));
    }

    private function formatValue(mixed $value): mixed
    {
        if ($value instanceof TranslatableInterface) {
            return $value->trans($this->translator);
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('d/m/Y H:i:s');
        }

        if (is_array($value)) {
            return implode(', ', $value);
        }

        if (is_object($value)) {
            if ($value instanceof Collection) {
                return implode(', ', array_map(static fn (mixed $item): string => (string) $item, $value->toArray()));
            }

            return method_exists($value, '__toString') ? (string) $value : null;
        }

        return $value;
    }

    /**
     * @param list<string> $propertyParts
     */
    private function resolveLastRelation(object $entity, array $propertyParts): ?object
    {
        $current = $entity;

        foreach (array_slice($propertyParts, 0, -1) as $part) {
            $current = $this->getValueFromPropertyName($current, $part);
            if (null === $current) {
                return null;
            }

            if (!is_object($current)) {
                throw new \RuntimeException(sprintf('Expected an object while resolving relation part "%s" on entity class "%s".', $part, $entity::class));
            }
        }

        return $current;
    }

    private function getValueFromPropertyName(object $entity, string $propertyName): mixed
    {
        $getter = 'get'.ucfirst($propertyName);
        if (method_exists($entity, $getter)) {
            return $entity->$getter();
        }

        if (property_exists($entity, $propertyName) && new \ReflectionProperty($entity, $propertyName)->isPublic()) {
            return $entity->$propertyName;
        }

        if (method_exists($entity, $propertyName)) {
            return $entity->$propertyName();
        }

        return null;
    }
}
