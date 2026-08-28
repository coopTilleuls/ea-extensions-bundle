<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\CrudFields;

use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use Symfony\Component\HttpFoundation\Request;

/**
 * One-time consumer setup helper: call CrudFieldLayout::applyIfEmbedded() from your
 * DashboardController's own configureCrud(), once, so an embedded CrudField's fetch renders
 * without the sidebar/topbar/full page chrome. This overrides the "layout" template — crud/index
 * itself (vendor's, or your theme's own override) keeps rendering 100% normally except for its
 * "filters" block (patched only to exclude "forced" filters — see hiddenFilters — from the
 * applied-filters count/reset link), so the table, action buttons/dropdowns and pagination all
 * keep their real markup/CSS/icons. Also appends a form theme so those same forced filters don't
 * show up as editable rows in the embed's own "Filtrer" modal (see HiddenFilterTypeExtension).
 * This is a one-time, app-wide setup step (not something every embeddable CrudController needs
 * to declare) — the same category as the bundle registration/importmap entry already required
 * for other features in this bundle.
 */
final class CrudFieldLayout
{
    public const REQUEST_ATTRIBUTE_PARENT_INDEX_TEMPLATE = '_ea_extensions_crud_field_parent_index_template';

    public static function applyIfEmbedded(Crud $crud, ?Request $request): Crud
    {
        if (null === $request || !$request->query->getBoolean(CrudField::QUERY_PARAM_CLEAN_LAYOUT)) {
            return $crud;
        }

        // Captured before overriding "crud/index" below, so embedded_index.html.twig can still
        // extend whichever crud/index would otherwise have been used (vendor's, or a theme's).
        $request->attributes->set(
            self::REQUEST_ATTRIBUTE_PARENT_INDEX_TEMPLATE,
            $crud->getAsDto()->getOverriddenTemplates()['crud/index'] ?? '@EasyAdmin/crud/index.html.twig',
        );

        $crud->overrideTemplate('layout', '@EAExtensions/crud_fields/embedded_layout.html.twig');
        $crud->overrideTemplate('crud/index', '@EAExtensions/crud_fields/embedded_index.html.twig');
        $crud->addFormTheme('@EAExtensions/crud_fields/filters_form_theme.html.twig');

        return $crud;
    }
}
