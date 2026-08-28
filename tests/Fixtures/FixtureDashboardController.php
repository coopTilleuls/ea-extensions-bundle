<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Fixtures;

use CoopTilleuls\Bundle\EAExtensionsBundle\CrudFields\CrudFieldLayout;
use CoopTilleuls\Bundle\EAExtensionsBundle\MenuOverrides\MenuItemTag;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * The menu built here is a 3-level-deep menu (Level 1 > Level 2 > leaf) whose two leaves both
 * link to {@see FixtureCrudController}'s index page, distinguished only by a {@see MenuItemTag}
 * tag — used to prove that both the infinite-depth expansion and the same-destination
 * disambiguation actually work end-to-end.
 *
 * "Untagged Sibling" also links to that same CRUD index but sits one level up, undistinguished —
 * it reproduces the exact shape of a regression where EasyAdmin's own CRUD-index fallback (meant
 * for pages with no exact URL match, e.g. edit/detail) would run independently *per tree level*
 * and match this shallower, untagged item purely by path even though a deeper, correctly tagged
 * leaf had already been exactly matched elsewhere in the tree.
 */
#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
final class FixtureDashboardController extends AbstractDashboardController
{
    public function __construct(
        private readonly RequestStack $requestStack,
    ) {
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new();
    }

    public function configureCrud(): Crud
    {
        return CrudFieldLayout::applyIfEmbedded(Crud::new(), $this->requestStack->getCurrentRequest());
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Home');

        yield MenuItem::linkTo(FixtureCrudController::class, 'Untagged Sibling');

        $leafA = MenuItem::linkTo(FixtureCrudController::class, 'Leaf A');
        MenuItemTag::new($leafA, 'variant-a');

        $leafB = MenuItem::linkTo(FixtureCrudController::class, 'Leaf B');
        MenuItemTag::new($leafB, 'variant-b');

        $level2 = MenuItem::subMenu('Level 2')->setSubItems([$leafA, $leafB]);

        yield MenuItem::subMenu('Level 1')->setSubItems([$level2]);
    }
}
