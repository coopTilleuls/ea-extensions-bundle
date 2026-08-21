<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\MenuOverrides\EasyAdmin;

use CoopTilleuls\Bundle\EAExtensionsBundle\MenuOverrides\MenuItemTag;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;

/**
 * A {@see MenuItemTag} tag only breaks the "same destination, two menu items" tie
 * for the request that literally follows the menu link. As soon as the visitor moves on to a
 * related page generated from there (e.g. clicking "Edit" on a CRUD row reached through one of
 * the two ambiguous entries), EasyAdmin regenerates that link by calling
 * {@see AdminUrlGeneratorInterface::unsetAllExcept()} to drop filters/sorting/pagination — which
 * would drop the disambiguation tag too unless it's explicitly kept, undoing the fix as soon as
 * the visitor leaves the exact URL the menu item points to.
 */
#[AsDecorator(decorates: AdminUrlGenerator::class)]
final readonly class MenuTagAwareAdminUrlGenerator implements \Stringable, AdminUrlGeneratorInterface
{
    public function __construct(
        #[AutowireDecorated]
        private AdminUrlGeneratorInterface $inner,
    ) {
    }

    public function __toString(): string
    {
        return $this->generateUrl();
    }

    public function setDashboard(string $dashboardControllerFqcn): AdminUrlGeneratorInterface
    {
        $this->inner->setDashboard($dashboardControllerFqcn);

        return $this;
    }

    public function setController(string $crudControllerFqcn): AdminUrlGeneratorInterface
    {
        $this->inner->setController($crudControllerFqcn);

        return $this;
    }

    public function setAction(string $action): AdminUrlGeneratorInterface
    {
        $this->inner->setAction($action);

        return $this;
    }

    public function setRoute(string $routeName, array $routeParameters = []): AdminUrlGeneratorInterface
    {
        $this->inner->setRoute($routeName, $routeParameters);

        return $this;
    }

    public function setEntityId(mixed $entityId): AdminUrlGeneratorInterface
    {
        $this->inner->setEntityId($entityId);

        return $this;
    }

    public function get(string $paramName): mixed
    {
        return $this->inner->get($paramName);
    }

    public function set(string $paramName, mixed $paramValue): AdminUrlGeneratorInterface
    {
        $this->inner->set($paramName, $paramValue);

        return $this;
    }

    public function setAll(array $routeParameters): AdminUrlGeneratorInterface
    {
        $this->inner->setAll($routeParameters);

        return $this;
    }

    public function unset(string $paramName): AdminUrlGeneratorInterface
    {
        $this->inner->unset($paramName);

        return $this;
    }

    public function unsetAll(): AdminUrlGeneratorInterface
    {
        $this->inner->unsetAll();

        return $this;
    }

    public function unsetAllExcept(string ...$namesOfParamsToKeep): AdminUrlGeneratorInterface
    {
        $namesOfParamsToKeep[] = MenuItemTag::QUERY_PARAMETER;

        $this->inner->unsetAllExcept(...$namesOfParamsToKeep);

        return $this;
    }

    public function generateUrl(): string
    {
        return $this->inner->generateUrl();
    }
}
