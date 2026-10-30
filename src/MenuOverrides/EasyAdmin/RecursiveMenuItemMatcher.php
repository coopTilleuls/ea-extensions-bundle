<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\MenuOverrides\EasyAdmin;

use CoopTilleuls\Bundle\EAExtensionsBundle\MenuOverrides\MenuHighlightAliasInterface;
use CoopTilleuls\Bundle\EAExtensionsBundle\MenuOverrides\MenuItemTag;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Controller\CrudControllerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Menu\MenuItemMatcherInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\MenuItemDto;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Replaces EasyAdmin's own {@see \EasyCorp\Bundle\EasyAdminBundle\Menu\MenuItemMatcher}, which
 * has two depth-related bugs once a menu nests beyond 2 levels or reuses the same destination
 * for two different items (exactly what {@see MenuItemTag} exists for):
 *
 * 1. Its "mark expanded" pass only ever looks one level down: a top-level item is expanded when
 *    one of its *direct* sub-items is selected, but a selected item nested three levels deep
 *    never causes its parent or grandparent to expand. Fixed by {@see doMarkExpandedMenuItem()}
 *    recursing into every level.
 * 2. Its exact-URL-match pass ("phase 1") and its looser CRUD-index fallback ("phase 2/3", used
 *    when nothing matches exactly — e.g. viewing an edit/detail page) are interleaved *per tree
 *    level*: each recursive call runs its own fallback whenever phase 1 found nothing *among its
 *    own direct siblings*, even if phase 1 already succeeded deeper in a different branch of the
 *    tree. Two menu items pointing to the same CRUD controller — one tagged, correctly matched by
 *    phase 1 deep in a submenu, and one untagged, sitting one level up — would then *both* end up
 *    selected: the untagged one via its own level's phase-2/3 fallback, which only compares plain
 *    paths and can't tell the two destinations apart. Fixed by running phase 1 once for the whole
 *    tree first, and only running the fallback afterwards, once, if phase 1 matched nothing at all.
 *
 * The CRUD-index fallback also honors {@see MenuHighlightAliasInterface}
 * when the current CRUD controller implements it, highlighting that controller's declared alias
 * instead of itself — for controllers that are never directly present in the menu (e.g. a
 * sub-resource of another entity).
 *
 * This is a full reimplementation of the interface (composition), not a subclass of EasyAdmin's
 * matcher: its methods are private, so a subclass could only shadow them with same-named new
 * methods that the parent's own logic never calls — an inheritance shape that looks like reuse
 * but silently is not.
 */
final readonly class RecursiveMenuItemMatcher implements MenuItemMatcherInterface
{
    public function __construct(
        private AdminUrlGeneratorInterface $adminUrlGenerator,
        // pagination.query_parameter, ignored like the sort or the search; null when pagination is disabled
        private ?string $pageSizeQueryParameter = null,
    ) {
    }

    /**
     * @param MenuItemDto[] $menuItems
     *
     * @return MenuItemDto[]
     */
    public function markSelectedMenuItem(array $menuItems, Request $request): array
    {
        // the URL with its filters is searched in the whole tree before the URL without them
        $foundExactMatch = false;
        foreach ($this->currentUrls($request) as $currentUrl) {
            if ($this->doMarkSelectedMenuItem($menuItems, $currentUrl)) {
                $foundExactMatch = true;

                break;
            }
        }

        if (!$foundExactMatch) {
            $this->applyCrudIndexFallback($menuItems, $request);
        }

        return $this->doMarkExpandedMenuItem($menuItems);
    }

    /**
     * The current URL both with its filters, matching a menu item that links to a pre-filtered
     * index, and without them, matching an unfiltered menu item once the visitor filters its index.
     * The first one is searched in the whole tree before the second, so that an unfiltered item
     * listed earlier doesn't win over the pre-filtered item linking to the exact current URL.
     *
     * @return list<string>
     */
    private function currentUrls(Request $request): array
    {
        $path = $request->getBasePath().$request->getPathInfo();
        $queryParams = $this->withoutListingParameters($request->query->all());
        ksort($queryParams);

        $withFilters = [] === $queryParams ? $path : $path.'?'.http_build_query($queryParams);

        unset($queryParams[EA::FILTERS]);
        $withoutFilters = [] === $queryParams ? $path : $path.'?'.http_build_query($queryParams);

        return [$withFilters, $withoutFilters];
    }

    /**
     * Exact-URL-match pass ("phase 1"), across the *whole* tree in one go: returns as soon as any
     * item anywhere matches, so a match found deep in one branch correctly prevents the caller
     * from also running the CRUD-index fallback for an unrelated, higher-level item.
     *
     * @param MenuItemDto[] $menuItems
     */
    private function doMarkSelectedMenuItem(array $menuItems, string $currentUrl): bool
    {
        foreach ($menuItems as $menuItemDto) {
            if ($menuItemDto->isMenuSection()) {
                continue;
            }

            if ($this->normalizedLinkUrl($menuItemDto) === $currentUrl) {
                $menuItemDto->setSelected(true);

                return true;
            }

            $subItems = $menuItemDto->getSubItems();
            if ([] !== $subItems && $this->doMarkSelectedMenuItem($subItems, $currentUrl)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Only reached when {@see doMarkSelectedMenuItem()} found no exact match anywhere: finds the
     * menu item(s) linking to the current CRUD controller's index page (e.g. so that visiting
     * /book/37/edit still highlights the "Book" index entry) — or, if that controller implements
     * {@see MenuHighlightAliasInterface}, the index page of the controller it declares instead
     * (e.g. so that visiting any page of a menu-less sub-resource controller highlights its
     * parent entity's entry).
     *
     * An item linking to the current page's own path wins over the index one (e.g. a custom
     * "summary" action reached with extra query parameters), and an item linking to another action
     * of the controller is used when none links to its index (e.g. a custom dashboard action).
     *
     * @param MenuItemDto[] $menuItems
     */
    private function applyCrudIndexFallback(array $menuItems, Request $request): void
    {
        $crudControllerFqcn = $request->attributes->get(EA::CRUD_CONTROLLER_FQCN);
        if (null === $crudControllerFqcn || !is_subclass_of($crudControllerFqcn, CrudControllerInterface::class)) {
            return;
        }

        $tag = $request->query->all()[MenuItemTag::QUERY_PARAMETER] ?? null;
        $tag = \is_string($tag) ? $tag : null;

        // an item linking to the current page itself, with the same tag, wins over the index item
        $currentPath = $request->getBasePath().$request->getPathInfo();
        if ($this->matchPathAndTagInMenuItems(static fn (string $linkPath): bool => $linkPath === $currentPath, $tag, $menuItems)) {
            return;
        }

        if (is_subclass_of($crudControllerFqcn, MenuHighlightAliasInterface::class)) {
            $crudControllerFqcn = $crudControllerFqcn::getMenuHighlightAlias();
        }

        // index URL with no query parameter: its path is used by every step below
        $indexUrlWithoutQueryParams = $this->adminUrlGenerator->unsetAll()->setAll([
            EA::DASHBOARD_CONTROLLER_FQCN => $request->attributes->get(EA::DASHBOARD_CONTROLLER_FQCN),
            EA::CRUD_CONTROLLER_FQCN => $crudControllerFqcn,
            EA::CRUD_ACTION => Action::INDEX,
        ])->generateUrl();

        $indexPath = parse_url($indexUrlWithoutQueryParams, \PHP_URL_PATH) ?: '';

        // e.g. the detail page of a book opened from a pre-filtered "featured books" index, whose
        // filters EasyAdmin carries over to the links of its rows
        $filters = $request->query->all()[EA::FILTERS] ?? null;
        if (\is_array($filters) && [] !== $filters && $this->matchPathAndTagInMenuItems(static fn (string $linkPath): bool => $linkPath === $indexPath, $tag, $menuItems, $filters)) {
            return;
        }

        // index URL keeping the current query parameters, except the listing ones and the filters
        $queryParams = $this->withoutListingParameters($request->query->all());
        unset($queryParams[EA::FILTERS]);

        $indexUrlWithQueryParams = $this->adminUrlGenerator->unsetAll()->setAll(array_merge($queryParams, [
            EA::DASHBOARD_CONTROLLER_FQCN => $request->attributes->get(EA::DASHBOARD_CONTROLLER_FQCN),
            EA::CRUD_CONTROLLER_FQCN => $crudControllerFqcn,
            EA::CRUD_ACTION => Action::INDEX,
        ]))->generateUrl();

        if ($this->matchUrlInMenuItems($indexUrlWithQueryParams, $menuItems)) {
            return;
        }

        // the index item, whatever its filters
        if ($this->matchPathAndTagInMenuItems(static fn (string $linkPath): bool => $linkPath === $indexPath, $tag, $menuItems)) {
            return;
        }

        // pretty URLs nest every action of a CRUD controller under its index path
        if ($this->matchPathAndTagInMenuItems(static fn (string $linkPath): bool => str_starts_with($linkPath, rtrim($indexPath, '/').'/'), $tag, $menuItems)) {
            return;
        }

        $this->matchUrlInMenuItems($indexUrlWithoutQueryParams, $menuItems);
    }

    /**
     * Ignores every query parameter of the menu item's link except its {@see MenuItemTag}, e.g. the
     * filters of an item linking to a pre-filtered index — unless $filters is given, in which case
     * the link's filters must be those ones.
     *
     * @param \Closure(string): bool       $pathMatches
     * @param MenuItemDto[]                $menuItems
     * @param array<array-key, mixed>|null $filters
     */
    private function matchPathAndTagInMenuItems(\Closure $pathMatches, ?string $tag, array $menuItems, ?array $filters = null): bool
    {
        foreach ($menuItems as $menuItemDto) {
            if ($menuItemDto->isMenuSection()) {
                continue;
            }

            $linkUrl = $menuItemDto->getLinkUrl();
            if (null !== $linkUrl && '' !== $linkUrl) {
                parse_str(parse_url($linkUrl, \PHP_URL_QUERY) ?: '', $linkQueryParams);
                $linkTag = $linkQueryParams[MenuItemTag::QUERY_PARAMETER] ?? null;

                // loose comparison: both filter arrays come from separately parsed URLs, so their keys may be ordered differently
                if ($linkTag === $tag
                    && (null === $filters || $filters == ($linkQueryParams[EA::FILTERS] ?? null))
                    && $pathMatches(parse_url($linkUrl, \PHP_URL_PATH) ?: '')) {
                    $menuItemDto->setSelected(true);

                    return true;
                }
            }

            $subItems = $menuItemDto->getSubItems();
            if ([] !== $subItems && $this->matchPathAndTagInMenuItems($pathMatches, $tag, $subItems, $filters)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param MenuItemDto[] $menuItems
     */
    private function matchUrlInMenuItems(string $urlToMatch, array $menuItems): bool
    {
        foreach ($menuItems as $menuItemDto) {
            if ($menuItemDto->isMenuSection()) {
                continue;
            }

            $linkUrl = $menuItemDto->getLinkUrl();
            if (null !== $linkUrl && '' !== $linkUrl && str_ends_with($urlToMatch, $linkUrl)) {
                $menuItemDto->setSelected(true);

                return true;
            }

            $subItems = $menuItemDto->getSubItems();
            if ([] !== $subItems && $this->matchUrlInMenuItems($urlToMatch, $subItems)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Removes the query parameters that only change how a list is displayed: sort, page, search and
     * page size.
     *
     * @param array<array-key, mixed> $queryParams
     *
     * @return array<array-key, mixed>
     */
    private function withoutListingParameters(array $queryParams): array
    {
        unset($queryParams[EA::SORT], $queryParams[EA::PAGE], $queryParams[EA::QUERY]);
        if (null !== $this->pageSizeQueryParameter) {
            unset($queryParams[$this->pageSizeQueryParameter]);
        }

        return $queryParams;
    }

    private function normalizedLinkUrl(MenuItemDto $menuItemDto): ?string
    {
        $linkUrl = $menuItemDto->getLinkUrl();
        if (null === $linkUrl || '' === $linkUrl) {
            return null;
        }

        $urlParts = parse_url($linkUrl) ?: [];
        $normalized = $urlParts['path'] ?? '';
        if (\array_key_exists('query', $urlParts)) {
            $normalized .= '?'.$urlParts['query'];
        }
        if (\array_key_exists('fragment', $urlParts)) {
            $normalized .= '#'.$urlParts['fragment'];
        }

        return $normalized;
    }

    /**
     * Recurses into every level, not just the top one, so a selection nested arbitrarily deep
     * expands every one of its ancestors, not just its immediate parent.
     *
     * @param MenuItemDto[] $menuItems
     *
     * @return MenuItemDto[]
     */
    private function doMarkExpandedMenuItem(array $menuItems): array
    {
        foreach ($menuItems as $menuItemDto) {
            $menuSubitems = $menuItemDto->getSubItems();

            if ([] === $menuSubitems) {
                continue;
            }

            $menuSubitems = $this->doMarkExpandedMenuItem($menuSubitems);
            $menuItemDto->setSubItems($menuSubitems);

            foreach ($menuSubitems as $submenuItem) {
                if ($submenuItem->isSelected() || $submenuItem->isExpanded()) {
                    $menuItemDto->setExpanded(true);

                    break;
                }
            }
        }

        return $menuItems;
    }
}
