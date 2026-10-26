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
 * Returns a page size outside the bundle-wide list (1000), but only when acting as an
 * autocomplete source: the index keeps the bundle defaults.
 *
 * @extends AbstractCrudController<FixtureEntity>
 */
final class FixtureAutocompletePaginationCrudController extends AbstractCrudController implements DefaultPageSizeProviderInterface
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
    public static function getDefaultPageSize(AdminContext $context): ?int
    {
        return 'autocomplete' === $context->getCrud()?->getCurrentAction() ? 1000 : null;
    }
}
