<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\CustomizableColumns\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * One checkbox per unlocked, authorized column, plus a "save as default" switch. Checked
 * columns are the ones currently selected; submission order of the checked fields becomes the
 * new column order (the "ea-extensions--column-handler" Stimulus controller reorders the DOM
 * rows client-side). Applying is always transient (the chosen columns only affect the current
 * page view, via a query parameter) — checking the switch is what makes it persist.
 *
 * @extends AbstractType<array<string, bool>>
 */
final class ColumnPreferenceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        foreach ($options['columns'] as $property => $label) {
            $builder->add($property, CheckboxType::class, [
                'label' => $label,
                'required' => false,
                'data' => in_array($property, $options['checked_columns'], true),
            ]);
        }

        $builder->add('save', CheckboxType::class, [
            'label' => 'Enregistrer comme paramètres par défaut',
            'required' => false,
            'attr' => [
                'role' => 'switch',
                'class' => 'form-check-input ms-0',
            ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_protection' => true,
            'columns' => [],
            'checked_columns' => [],
        ]);
        $resolver->setAllowedTypes('columns', 'array');
        $resolver->setAllowedTypes('checked_columns', 'array');
    }
}
