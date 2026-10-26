<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Fixtures;

use CoopTilleuls\Bundle\EAExtensionsBundle\Pagination\DefaultPageSizeProviderInterface;
use CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Fixtures\Entity\FixtureEntity;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/**
 * Deliberately misconfigured: declares only a default page size, so the selector falls back
 * to the bundle-wide list ([10, 25, 50, 100] in the test kernel) — and the returned default
 * (15) isn't a member of that list. Used to assert PaginationCrudListener catches this case
 * too, not just when both the default and the list are declared together.
 *
 * @extends AbstractCrudController<FixtureEntity>
 */
final class FixtureDefaultOnlyMismatchedPaginationCrudController extends AbstractCrudController implements DefaultPageSizeProviderInterface
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
}
