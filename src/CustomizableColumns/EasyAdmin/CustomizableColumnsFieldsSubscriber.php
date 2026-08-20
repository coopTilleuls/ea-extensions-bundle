<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\CustomizableColumns\EasyAdmin;

use CoopTilleuls\Bundle\EAExtensionsBundle\CustomizableColumns\ColumnSelectionResolver;
use CoopTilleuls\Bundle\EAExtensionsBundle\CustomizableColumns\CustomizableColumnsConfigurationInterface;
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

        $lockedColumns = [];
        $defaultColumns = [];
        if (is_subclass_of($crudControllerFqcn, CustomizableColumnsConfigurationInterface::class)) {
            $lockedColumns = $crudControllerFqcn::getLockedColumns();
            $defaultColumns = $crudControllerFqcn::getDefaultColumns();
        }

        foreach ($entities as $entityDto) {
            $fields = $entityDto->getFields();
            if (null === $fields || $fields->isEmpty()) {
                continue;
            }

            /** @var list<string> $availableProperties */
            $availableProperties = [];
            /** @var array<string, FieldDto> $fieldsByProperty */
            $fieldsByProperty = [];
            foreach ($fields as $fieldDto) {
                $availableProperties[] = $fieldDto->getProperty();
                $fieldsByProperty[$fieldDto->getProperty()] = $fieldDto;
            }

            $selectedColumns = $this->resolver->resolve(
                $context->getRequest(),
                $crudControllerFqcn,
                $context->getRequest()->query->getString(ColumnSelectionResolver::QUERY_PARAM),
                $availableProperties,
                [] === $defaultColumns ? $availableProperties : $defaultColumns,
                $lockedColumns,
            );

            if ([] === $selectedColumns) {
                continue;
            }

            foreach ($fieldsByProperty as $fieldDto) {
                $fields->unset($fieldDto);
            }
            foreach ($selectedColumns as $property) {
                $fields->add($fieldsByProperty[$property]);
            }
        }
    }
}
