<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\EasyAdmin;

use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Filter\FilterInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FilterDataDto;
use EasyCorp\Bundle\EasyAdminBundle\Filter\FilterTrait;
use EasyCorp\Bundle\EasyAdminBundle\Form\Filter\Type\TextFilterType;
use Symfony\Contracts\Translation\TranslatableInterface;

/**
 * EasyAdmin's TextFilter, compared case-insensitively (its LIKE is case-sensitive on PostgreSQL).
 */
final class CaseInsensitiveTextFilter implements FilterInterface
{
    use FilterTrait;

    public static function new(string $propertyName, TranslatableInterface|string|false|null $label = null): self
    {
        return new self()
            ->setFilterFqcn(self::class)
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setFormType(TextFilterType::class)
            ->setFormTypeOption('translation_domain', 'EasyAdminBundle');
    }

    public function apply(QueryBuilder $queryBuilder, FilterDataDto $filterDataDto, ?FieldDto $fieldDto, EntityDto $entityDto): void
    {
        $queryBuilder->andWhere(\sprintf(
            'LOWER(%s.%s) %s LOWER(:%s)',
            $filterDataDto->getEntityAlias(),
            $filterDataDto->getProperty(),
            $filterDataDto->getComparison(),
            $filterDataDto->getParameterName(),
        ))
            ->setParameter($filterDataDto->getParameterName(), $filterDataDto->getValue());
    }
}
