<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\CrudFields\EasyAdmin;

use CoopTilleuls\Bundle\EAExtensionsBundle\CrudFields\CrudField;
use EasyCorp\Bundle\EasyAdminBundle\Form\Filter\Type\ArrayFilterType;
use EasyCorp\Bundle\EasyAdminBundle\Form\Filter\Type\BooleanFilterType;
use EasyCorp\Bundle\EasyAdminBundle\Form\Filter\Type\ChoiceFilterType;
use EasyCorp\Bundle\EasyAdminBundle\Form\Filter\Type\ComparisonFilterType;
use EasyCorp\Bundle\EasyAdminBundle\Form\Filter\Type\DateTimeFilterType;
use EasyCorp\Bundle\EasyAdminBundle\Form\Filter\Type\EntityFilterType;
use EasyCorp\Bundle\EasyAdminBundle\Form\Filter\Type\NullFilterType;
use EasyCorp\Bundle\EasyAdminBundle\Form\Filter\Type\NumericFilterType;
use EasyCorp\Bundle\EasyAdminBundle\Form\Filter\Type\TextFilterType;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Marks a filter's own root form field as "hidden" (see filters_form_theme.html.twig's
 * ea_filters_widget override) whenever its property name is listed in CrudField's
 * hiddenFilters query parameter — i.e. it's a "forced" filter baked into the embed's fetch URL
 * via overrideIndexUrl(), not something the end user applied themselves, so it has no business
 * showing up as an editable/removable row in the "Filtrer" modal.
 */
final class HiddenFilterTypeExtension extends AbstractTypeExtension
{
    public function __construct(
        private readonly RequestStack $requestStack,
    ) {
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $hiddenFilters = $this->requestStack->getCurrentRequest()?->query->all(CrudField::QUERY_PARAM_HIDDEN_FILTERS) ?? [];

        $view->vars['hidden_filter'] = \in_array($form->getName(), $hiddenFilters, true);
    }

    public static function getExtendedTypes(): iterable
    {
        return [
            ArrayFilterType::class,
            BooleanFilterType::class,
            ChoiceFilterType::class,
            ComparisonFilterType::class,
            DateTimeFilterType::class,
            EntityFilterType::class,
            NullFilterType::class,
            NumericFilterType::class,
            TextFilterType::class,
        ];
    }
}
