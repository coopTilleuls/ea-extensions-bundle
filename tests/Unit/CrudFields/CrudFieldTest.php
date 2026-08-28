<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Unit\CrudFields;

use CoopTilleuls\Bundle\EAExtensionsBundle\CrudFields\CrudField;
use CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Fixtures\FixtureEmbeddedTargetCrudController;
use PHPUnit\Framework\TestCase;

final class CrudFieldTest extends TestCase
{
    public function testDefaults(): void
    {
        $field = CrudField::new('embed')->getAsDto();

        self::assertFalse($field->getCustomOption(CrudField::OPTION_HIDE_GLOBAL_ACTIONS));
        self::assertFalse($field->getCustomOption(CrudField::OPTION_HIDE_ENTITY_ACTIONS));
        self::assertFalse($field->getCustomOption(CrudField::OPTION_DISPLAY_AS_CARD));
        self::assertFalse($field->getCustomOption(CrudField::OPTION_LAZY));
        self::assertFalse($field->getCustomOption(CrudField::OPTION_SHOW_FILTERS));
        self::assertFalse($field->getCustomOption(CrudField::OPTION_OPEN_ACTIONS_IN_MODAL));
        self::assertSame([], $field->getCustomOption(CrudField::OPTION_ADDITIONAL_ACTIONS));
        self::assertSame([], $field->getCustomOption(CrudField::OPTION_CRUD_COLUMNS));
    }

    public function testSetControllerStoresFqcnAndShortName(): void
    {
        $field = CrudField::new('embed')
            ->setController(FixtureEmbeddedTargetCrudController::class)
            ->getAsDto()
        ;

        self::assertSame(FixtureEmbeddedTargetCrudController::class, $field->getCustomOption(CrudField::OPTION_CRUD_CONTROLLER));
        self::assertSame('FixtureEmbeddedTargetCrudController', $field->getCustomOption(CrudField::OPTION_CRUD_CONTROLLER_NAME));
    }

    public function testAddIndexActionRegistersASeeAllAction(): void
    {
        $field = CrudField::new('embed')->addIndexAction()->getAsDto();

        $actions = $field->getCustomOption(CrudField::OPTION_ADDITIONAL_ACTIONS);

        self::assertCount(1, $actions);
        self::assertSame(CrudField::ACTION_SEE_ALL, $actions[0]->getName());
    }

    public function testAddActionIgnoresNull(): void
    {
        $field = CrudField::new('embed')->addAction(null)->getAsDto();

        self::assertSame([], $field->getCustomOption(CrudField::OPTION_ADDITIONAL_ACTIONS));
    }

    public function testFluentTogglesStoreCustomOptions(): void
    {
        $field = CrudField::new('embed')
            ->hideGlobalActions()
            ->hideEntityActions()
            ->displayAsCard()
            ->lazy()
            ->showFilters()
            ->openActionsInModal()
            ->setCrudColumns(['id', 'title'])
            ->getAsDto()
        ;

        self::assertTrue($field->getCustomOption(CrudField::OPTION_HIDE_GLOBAL_ACTIONS));
        self::assertTrue($field->getCustomOption(CrudField::OPTION_HIDE_ENTITY_ACTIONS));
        self::assertTrue($field->getCustomOption(CrudField::OPTION_DISPLAY_AS_CARD));
        self::assertTrue($field->getCustomOption(CrudField::OPTION_LAZY));
        self::assertTrue($field->getCustomOption(CrudField::OPTION_SHOW_FILTERS));
        self::assertTrue($field->getCustomOption(CrudField::OPTION_OPEN_ACTIONS_IN_MODAL));
        self::assertSame(['id', 'title'], $field->getCustomOption(CrudField::OPTION_CRUD_COLUMNS));
    }

    public function testTogglesCanBeExplicitlySetBackToFalse(): void
    {
        $field = CrudField::new('embed')
            ->hideGlobalActions()
            ->hideGlobalActions(false)
            ->getAsDto()
        ;

        self::assertFalse($field->getCustomOption(CrudField::OPTION_HIDE_GLOBAL_ACTIONS));
    }
}
