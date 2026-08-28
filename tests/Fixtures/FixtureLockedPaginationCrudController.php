<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Fixtures;

use CoopTilleuls\Bundle\EAExtensionsBundle\Pagination\PaginationConfigurationInterface;
use CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Fixtures\Entity\FixtureEntity;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/**
 * A locked page size (empty available-sizes list): the selector must stay hidden and the
 * query-string override must be ignored.
 *
 * @extends AbstractCrudController<FixtureEntity>
 */
final class FixtureLockedPaginationCrudController extends AbstractCrudController implements PaginationConfigurationInterface
{
    public static function getEntityFqcn(): string
    {
        return FixtureEntity::class;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id');
        yield TextField::new('title');
    }

    /**
     * @param AdminContext<object> $context
     */
    public static function getDefaultPageSize(AdminContext $context): int
    {
        return 3;
    }

    /**
     * @param AdminContext<object> $context
     *
     * @return list<int>
     */
    public static function getAvailablePageSizes(AdminContext $context): array
    {
        return [];
    }
}
