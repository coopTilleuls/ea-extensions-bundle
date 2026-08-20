<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Fixtures;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;

#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
final class FixtureDashboardController extends AbstractDashboardController
{
    public function configureDashboard(): Dashboard
    {
        return Dashboard::new();
    }
}
