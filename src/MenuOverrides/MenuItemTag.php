<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\MenuOverrides;

use EasyCorp\Bundle\EasyAdminBundle\Contracts\Menu\MenuItemInterface;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;

/**
 * Disambiguates two menu items that link to the exact same destination (same controller/action,
 * or same route), so that only the intended one gets marked as selected/active.
 *
 * EasyAdmin picks the "active" menu item purely by comparing the current request's URL against
 * each menu item's generated URL: if two items generate the same URL, whichever one happens to
 * be listed first always wins, regardless of which one the user actually navigated through.
 *
 * Tagging each ambiguous item at the point it's declared bakes a discriminator into its
 * generated URL (as an ordinary EasyAdmin route parameter), which
 * {@see EasyAdmin\RecursiveMenuItemMatcher} then uses to break the tie. There is no separate
 * registry mapping controllers to tags to keep in sync — the correction lives entirely at the
 * item's own declaration.
 *
 * {@see new()} tags a menu item itself. {@see applyTo()}/{@see appendToUrl()} tag any *other*
 * link that should land on an already-tagged menu item's exact URL — e.g. a link generated from
 * inside a CrudController action, or an embedded field link — pointing at the same destination
 * as one of your tagged menu items, so that following it highlights that item instead of nothing
 * (or the wrong item).
 */
final class MenuItemTag
{
    public const string QUERY_PARAMETER = 'menuEntry';

    private function __construct()
    {
    }

    /**
     * @template T of MenuItemInterface
     *
     * @param T $menuItem
     *
     * @return T
     */
    public static function new(MenuItemInterface $menuItem, string $key): MenuItemInterface
    {
        $menuItem->getAsDto()->setRouteParameter(self::QUERY_PARAMETER, $key);

        return $menuItem;
    }

    /**
     * Tags a URL you're generating yourself (e.g. in a controller action, before redirecting, or
     * building a custom link) with the same key as a {@see new()}-tagged menu item, so landing on
     * it highlights that menu item.
     */
    public static function applyTo(AdminUrlGeneratorInterface $urlGenerator, string $key): AdminUrlGeneratorInterface
    {
        return $urlGenerator->set(self::QUERY_PARAMETER, $key);
    }

    /**
     * Same as {@see applyTo()}, for a plain URL string (e.g. one already generated, or rendered
     * in a template) instead of an in-progress {@see AdminUrlGeneratorInterface} call chain.
     */
    public static function appendToUrl(string $url, string $key): string
    {
        $separator = str_contains($url, '?') ? '&' : '?';

        return $url.$separator.http_build_query([self::QUERY_PARAMETER => $key]);
    }
}
