<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\CustomizableColumns\EasyAdmin;

use CoopTilleuls\Bundle\EAExtensionsBundle\CustomizableColumns\ColumnName;
use CoopTilleuls\Bundle\EAExtensionsBundle\CustomizableColumns\ColumnsConfigurationResolver;
use CoopTilleuls\Bundle\EAExtensionsBundle\CustomizableColumns\ColumnSelectionResolver;
use EasyCorp\Bundle\EasyAdminBundle\Collection\EntityCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use EasyCorp\Bundle\EasyAdminBundle\Event\AfterCrudActionEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: AfterCrudActionEvent::class)]
final class CustomizableColumnsFieldsSubscriber
{
    public function __construct(
        private readonly ColumnSelectionResolver $resolver,
        private readonly ColumnsConfigurationResolver $configurationResolver,
    ) {
    }

    public function __invoke(AfterCrudActionEvent $event): void
    {
        $context = $event->getAdminContext();
        if (null === $context || Crud::PAGE_INDEX !== $context->getCrud()?->getCurrentPage()) {
            return;
        }

        $crudControllerFqcn = $context->getCrud()->getControllerFqcn();
        if (null === $crudControllerFqcn) {
            return;
        }

        $entities = $event->getResponseParameters()->get('entities');
        if (!$entities instanceof EntityCollection) {
            return;
        }

        [$lockedColumns, $defaultColumns] = $this->configurationResolver->resolve($crudControllerFqcn, $context->getRequest());

        foreach ($entities as $entityDto) {
            $fields = $entityDto->getFields();
            if (null === $fields || $fields->isEmpty()) {
                continue;
            }

            /** @var array<string, FieldDto> $fieldsByColumn */
            $fieldsByColumn = [];
            foreach ($fields as $fieldDto) {
                $fieldsByColumn[ColumnName::of($fieldDto)] = $fieldDto;
            }
            $availableColumns = array_keys($fieldsByColumn);

            $selectedColumns = $this->resolver->resolve(
                $context->getRequest(),
                $crudControllerFqcn,
                $context->getRequest()->query->getString(ColumnSelectionResolver::QUERY_PARAM),
                $availableColumns,
                [] === $defaultColumns ? $availableColumns : $defaultColumns,
                $lockedColumns,
            );

            if ([] === $selectedColumns) {
                continue;
            }

            foreach ($fieldsByColumn as $fieldDto) {
                $fields->unset($fieldDto);
            }
            foreach ($selectedColumns as $column) {
                $fields->add($fieldsByColumn[$column]);
            }
        }
    }
}
