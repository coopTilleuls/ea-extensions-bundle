<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\CrudFields\Form;

use Symfony\Component\Form\AbstractType;

/**
 * A no-op form type backing CrudField (mapped: false) — CrudField carries no editable value
 * of its own, this only exists so EasyAdmin has something to render if the field is ever
 * displayed on a new/edit page instead of onlyOnDetail().
 *
 * @extends AbstractType<null>
 */
final class CrudFieldType extends AbstractType
{
    public function getBlockPrefix(): string
    {
        return 'ea_crud_field';
    }
}
