<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\MenuOverrides;

/**
 * Optional contract for a CrudController that isn't itself present in the menu — e.g. it manages
 * a sub-resource reached only through another entity's pages (a "Transfer" or "Payment"
 * controller with no menu entry of its own) — so that visiting any of its pages still highlights
 * a specific, meaningful menu entry instead of highlighting nothing.
 *
 * Declared on the controller that needs it, not in a separate central mapping: there is nothing
 * to keep in sync, and nothing to forget when a new controller is added later.
 *
 * Only affects {@see EasyAdmin\RecursiveMenuItemMatcher}'s CRUD-index fallback (used when no
 * menu item exactly matches the current page, e.g. an edit/detail/new page) — it has no effect
 * when this controller's own pages *are* matched exactly by one of your menu items.
 */
interface MenuHighlightAliasInterface
{
    /**
     * The FQCN of the CrudController whose index menu entry should be highlighted instead of this
     * one's own. Not typed as `class-string<CrudControllerInterface>` because that interface's
     * generic entity type parameter isn't covariant, which would reject any concrete controller's
     * (necessarily more specific) return type.
     */
    public static function getMenuHighlightAlias(): string;
}
