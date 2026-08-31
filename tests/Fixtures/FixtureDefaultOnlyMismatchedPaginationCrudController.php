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
 * Deliberately misconfigured: overrides only getDefaultPageSize(), leaving
 * getAvailablePageSizes() at null (falls back to the bundle-wide list, [10, 25, 50, 100] in
 * the test kernel) — the returned default (15) isn't a member of that list either. Used to
 * assert PaginationCrudListener catches this case too, not just when both methods are
 * overridden together.
 *
 * @extends AbstractCrudController<FixtureEntity>
 */
final class FixtureDefaultOnlyMismatchedPaginationCrudController extends AbstractCrudController implements PaginationConfigurationInterface
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
        return 15;
    }

    /**
     * @param AdminContext<object> $context
     *
     * @return list<int>|null
     */
    public static function getAvailablePageSizes(AdminContext $context): ?array
    {
        return null;
    }
}
