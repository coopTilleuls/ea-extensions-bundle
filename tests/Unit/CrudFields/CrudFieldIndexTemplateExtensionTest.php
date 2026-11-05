<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Unit\CrudFields;

use CoopTilleuls\Bundle\EAExtensionsBundle\CrudFields\EasyAdmin\CrudFieldIndexTemplateExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class CrudFieldIndexTemplateExtensionTest extends TestCase
{
    public function testFiltersButtonNeedsBothTheOptionAndConfiguredFilters(): void
    {
        self::assertTrue($this->extension(['showFilters' => '1'])->hasFiltersButton(['title']));

        // The target controller may well have filters of its own; they only surface in an embed
        // that asked for them.
        self::assertFalse($this->extension([])->hasFiltersButton(['title']));
        self::assertFalse($this->extension(['showFilters' => '1'])->hasFiltersButton([]));
    }

    public function testPageActionsAreEmptyOnlyWhenNothingIsLeftToShow(): void
    {
        $extension = $this->extension([]);

        self::assertFalse($extension->hasPageActions([], [], []));
        self::assertTrue($extension->hasPageActions(['new'], [], []));
        self::assertTrue($extension->hasPageActions([], ['delete'], []));
    }

    public function testFiltersButtonAloneKeepsTheHeader(): void
    {
        self::assertTrue($this->extension(['showFilters' => '1'])->hasPageActions([], [], ['title']));
        self::assertFalse($this->extension([])->hasPageActions([], [], ['title']));
    }

    public function testNoRequestMeansNoFiltersButton(): void
    {
        $extension = new CrudFieldIndexTemplateExtension(new RequestStack());

        self::assertFalse($extension->hasFiltersButton(['title']));
        self::assertFalse($extension->hasPageActions([], [], ['title']));
    }

    /**
     * @param array<string, string> $query
     */
    private function extension(array $query): CrudFieldIndexTemplateExtension
    {
        $requestStack = new RequestStack();
        $requestStack->push(new Request($query));

        return new CrudFieldIndexTemplateExtension($requestStack);
    }
}
