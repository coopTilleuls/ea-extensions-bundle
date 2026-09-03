<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\EasyAdmin;

use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Filter\FilterInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FilterDataDto;
use EasyCorp\Bundle\EasyAdminBundle\Filter\FilterTrait;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

class NotificationTreatmentFilter implements FilterInterface
{
    use FilterTrait;

    public static function new(?string $label = null): self
    {
        return new self()
            ->setFilterFqcn(self::class)
            ->setProperty('treatment')
            ->setLabel($label)
            ->setFormType(ChoiceType::class)
            ->setFormTypeOptions([
                'mapped' => false,
                'choices' => [
                    'Traitée' => false,
                    'Non traitée' => true,
                    'Non traitable' => null,
                ],
                'multiple' => false,
                'expanded' => false,
            ]);
    }

    public function apply(QueryBuilder $queryBuilder, FilterDataDto $filterDataDto, ?FieldDto $fieldDto, EntityDto $entityDto): void
    {
        $alias = $filterDataDto->getEntityAlias();
        $aliases = $queryBuilder->getAllAliases();

        $filterValue = $filterDataDto->getValue();

        if (null === $filterValue) {
            $queryBuilder->andWhere("$alias.treatment IS NULL");

            return;
        }

        if (!\in_array('nt', $aliases, true)) {
            $queryBuilder->innerJoin("$alias.treatment", 'nt');
        }

        $queryBuilder->andWhere('nt.pending = :pending')
            ->setParameter('pending', $filterValue);
    }
}
