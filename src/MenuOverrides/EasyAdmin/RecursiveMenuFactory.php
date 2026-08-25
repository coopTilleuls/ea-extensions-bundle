<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\MenuOverrides\EasyAdmin;

use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use EasyCorp\Bundle\EasyAdminBundle\Config\UserMenu;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Controller\CrudControllerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Controller\DashboardControllerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Factory\MenuFactoryInterface;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Menu\MenuItemInterface;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Menu\MenuItemMatcherInterface;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Provider\AdminContextProviderInterface;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Translation\EntityTranslationIdGeneratorInterface;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\MainMenuDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\MenuItemDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\UserMenuDto;
use EasyCorp\Bundle\EasyAdminBundle\Factory\MenuFactory;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use EasyCorp\Bundle\EasyAdminBundle\Security\Permission;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Http\Logout\LogoutUrlGenerator;
use Symfony\Contracts\Translation\TranslatableInterface;

use function Symfony\Component\Translation\t;

/**
 * EasyAdmin's own {@see MenuFactory::createMainMenu()} only ever processes two levels of
 * depth: it builds the top-level items and, for each one, its direct sub-items — but any
 * sub-item that is itself a submenu has its own sub-items silently discarded (built with an
 * empty array). This decorator replaces that two-level walk with a genuinely recursive one,
 * so a menu can nest as deep as a consumer declares it.
 *
 * It reimplements {@see MenuFactory}'s private building logic rather than extending it,
 * because that logic is private and therefore cannot be selectively overridden by a subclass
 * without silently reimplementing the whole class — see the bundle's menu-overrides doc for
 * why that inheritance shape was rejected.
 */
#[AsDecorator(decorates: MenuFactory::class)]
final readonly class RecursiveMenuFactory implements MenuFactoryInterface
{
    public function __construct(
        #[AutowireDecorated]
        private MenuFactoryInterface $inner,
        private AdminContextProviderInterface $adminContextProvider,
        private AuthorizationCheckerInterface $authChecker,
        private AdminUrlGeneratorInterface $adminUrlGenerator,
        private MenuItemMatcherInterface $menuItemMatcher,
        #[Autowire(service: 'security.logout_url_generator')]
        private LogoutUrlGenerator $logoutUrlGenerator,
        private ?EntityTranslationIdGeneratorInterface $entityTranslationIdGenerator = null,
    ) {
    }

    /**
     * @param array<MenuItemDto|MenuItemInterface> $menuItems
     */
    public function createMainMenu(array $menuItems): MainMenuDto
    {
        /** @var AdminContext<object> $adminContext */
        $adminContext = $this->adminContextProvider->getContext();
        $translationDomain = $adminContext->getI18n()->getTranslationDomain();

        $builtItems = $this->buildMenuItems($menuItems, $translationDomain, $adminContext->isUseEntityTranslations());
        $builtItems = $this->menuItemMatcher->markSelectedMenuItem($builtItems, $adminContext->getRequest());

        return new MainMenuDto($builtItems);
    }

    public function createUserMenu(UserMenu $userMenu): UserMenuDto
    {
        return $this->inner->createUserMenu($userMenu);
    }

    /**
     * @param array<MenuItemDto|MenuItemInterface> $menuItems
     *
     * @return MenuItemDto[]
     */
    private function buildMenuItems(array $menuItems, string $translationDomain, bool $isUseEntityTranslations): array
    {
        $builtItems = [];
        foreach ($menuItems as $menuItem) {
            $menuItemDto = $menuItem instanceof MenuItemDto ? $menuItem : $menuItem->getAsDto();

            if (false === $this->authChecker->isGranted(Permission::EA_VIEW_MENU_ITEM, $menuItemDto)) {
                continue;
            }

            $subItems = $this->buildMenuItems($menuItemDto->getSubItems(), $translationDomain, $isUseEntityTranslations);

            $builtItems[] = $this->buildMenuItem($menuItemDto, $subItems, $translationDomain, $isUseEntityTranslations);
        }

        return $builtItems;
    }

    /**
     * @param MenuItemDto[] $subItems
     */
    private function buildMenuItem(MenuItemDto $menuItemDto, array $subItems, string $translationDomain, bool $isUseEntityTranslations): MenuItemDto
    {
        if (!$menuItemDto->getLabel() instanceof TranslatableInterface) {
            $label = $menuItemDto->getLabel();
            $routeParameters = $menuItemDto->getRouteParameters() ?? [];

            if (null === $label && MenuItemDto::TYPE_CRUD === $menuItemDto->getType()) {
                $entityFqcn = $routeParameters[EA::ENTITY_FQCN];
                if ($isUseEntityTranslations && null !== $this->entityTranslationIdGenerator) {
                    $action = $routeParameters[EA::CRUD_ACTION] ?? Action::INDEX;
                    $label = Action::INDEX === $action
                        ? $this->entityTranslationIdGenerator->generateForEntity($entityFqcn, false)
                        : $this->entityTranslationIdGenerator->generateForEntity($entityFqcn, true);
                } else {
                    $label = basename(str_replace('\\', '/', $entityFqcn));
                }
            } elseif (null === $label && MenuItemDto::TYPE_CONTROLLER === $menuItemDto->getType()) {
                $controllerFqcn = $routeParameters[EA::CRUD_CONTROLLER_FQCN];
                if ($isUseEntityTranslations && null !== $this->entityTranslationIdGenerator && is_a($controllerFqcn, CrudControllerInterface::class, true)) {
                    $entityFqcn = $controllerFqcn::getEntityFqcn();
                    $action = $routeParameters[EA::CRUD_ACTION] ?? Action::INDEX;
                    $label = Action::INDEX === $action
                        ? $this->entityTranslationIdGenerator->generateForEntity($entityFqcn, false)
                        : $this->entityTranslationIdGenerator->generateForEntity($entityFqcn, true);
                } else {
                    $label = basename(str_replace('\\', '/', (string) $controllerFqcn));
                    $label = preg_replace('/(Crud)?Controller$/', '', $label);
                }
            } else {
                $label = '' === $label || null === $label ? $label : t($label, $menuItemDto->getTranslationParameters(), $translationDomain);
            }
            $menuItemDto->setLabel($label);
        }

        $menuItemDto->setLinkUrl($this->generateMenuItemUrl($menuItemDto));
        $menuItemDto->setSubItems($subItems);

        if ('' === $menuItemDto->getLinkRel() && MenuItemDto::TYPE_URL === $menuItemDto->getType()) {
            $menuItemDto->setLinkRel('noopener');
        }

        return $menuItemDto;
    }

    private function generateMenuItemUrl(MenuItemDto $menuItemDto): string
    {
        $menuItemType = $menuItemDto->getType();

        if (MenuItemDto::TYPE_CRUD === $menuItemType) {
            $routeParameters = $menuItemDto->getRouteParameters() ?? [];

            $this->adminUrlGenerator->unsetAll()->setAll($routeParameters);

            $entityFqcn = $routeParameters[EA::ENTITY_FQCN] ?? null;
            $crudControllerFqcn = $routeParameters[EA::CRUD_CONTROLLER_FQCN] ?? null;
            if (null === $entityFqcn && null === $crudControllerFqcn) {
                throw new \RuntimeException('A CRUD menu item must define either the entity FQCN (using the third constructor argument) or the CRUD Controller FQCN (using the "setController()" method).');
            }

            if (null !== $crudControllerFqcn) {
                $this->adminUrlGenerator->setController($crudControllerFqcn);
            } else {
                $adminControllers = $this->adminContextProvider->getContext()?->getAdminControllers();
                $controllerFqcn = $adminControllers?->findCrudControllerByEntity($entityFqcn);
                if (null === $controllerFqcn) {
                    throw new \RuntimeException(sprintf('Unable to find the controller related to the "%s" Entity; did you forget to extend "%s"?', $entityFqcn, AbstractCrudController::class));
                }

                $this->adminUrlGenerator->setController($controllerFqcn);
                $this->adminUrlGenerator->unset(EA::ENTITY_FQCN);
            }

            return $this->adminUrlGenerator->generateUrl();
        }

        if (MenuItemDto::TYPE_CONTROLLER === $menuItemType) {
            $routeParameters = $menuItemDto->getRouteParameters() ?? [];

            $this->adminUrlGenerator->unsetAll()->setAll($routeParameters);

            $controllerFqcn = $routeParameters[EA::CRUD_CONTROLLER_FQCN];
            \assert(\is_string($controllerFqcn));

            if (is_a($controllerFqcn, DashboardControllerInterface::class, true)) {
                return $this->adminUrlGenerator->setDashboard($controllerFqcn)->generateUrl();
            }

            $action = $routeParameters[EA::CRUD_ACTION] ?? null;
            if (null === $action) {
                if (is_a($controllerFqcn, CrudControllerInterface::class, true)) {
                    $action = Action::INDEX;
                } elseif (method_exists($controllerFqcn, '__invoke')) {
                    $action = '__invoke';
                } else {
                    throw new \RuntimeException(sprintf('The menu item that links to the "%s" controller must call "->setAction()" to specify which action to link to, because the controller defines multiple actions and is not invokable.', $controllerFqcn));
                }
            }

            $this->adminUrlGenerator->setController($controllerFqcn);
            $this->adminUrlGenerator->setAction($action);

            return $this->adminUrlGenerator->generateUrl();
        }

        if (MenuItemDto::TYPE_DASHBOARD === $menuItemType) {
            return $this->adminUrlGenerator->unsetAll()->generateUrl();
        }

        if (MenuItemDto::TYPE_ROUTE === $menuItemType) {
            $routeName = $menuItemDto->getRouteName();
            if (null === $routeName) {
                throw new \RuntimeException('A route menu item must define a route name.');
            }

            return $this->adminUrlGenerator->unsetAll()->setRoute($routeName, $menuItemDto->getRouteParameters() ?? [])->generateUrl();
        }

        if (MenuItemDto::TYPE_SECTION === $menuItemType) {
            return '#';
        }

        if (MenuItemDto::TYPE_URL === $menuItemType) {
            return $menuItemDto->getLinkUrl() ?? '';
        }

        if (MenuItemDto::TYPE_LOGOUT === $menuItemType) {
            return $this->logoutUrlGenerator->getLogoutPath();
        }

        if (MenuItemDto::TYPE_EXIT_IMPERSONATION === $menuItemType) {
            // the switch parameter name can be changed, but this code assumes it's always
            // the default one because Symfony doesn't provide a generic exitImpersonationUrlGenerator
            return '?_switch_user=_exit';
        }

        return '';
    }
}
