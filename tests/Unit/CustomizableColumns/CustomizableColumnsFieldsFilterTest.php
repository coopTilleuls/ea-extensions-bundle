<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Unit\CustomizableColumns;

use CoopTilleuls\Bundle\EAExtensionsBundle\CustomizableColumns\ColumnName;
use CoopTilleuls\Bundle\EAExtensionsBundle\CustomizableColumns\ColumnSelectionResolver;
use CoopTilleuls\Bundle\EAExtensionsBundle\CustomizableColumns\CustomizableColumnsFieldsFilter;
use CoopTilleuls\Bundle\EAExtensionsBundle\CustomizableColumns\Storage\ColumnPreferenceStorageInterface;
use CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Fixtures\FixtureCrudController;
use CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Fixtures\FixtureCrudControllerWithColumnNames;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class CustomizableColumnsFieldsFilterTest extends TestCase
{
    public function testKeepsDefaultColumnsInTheirOrder(): void
    {
        $filter = $this->createFilter(new Request());

        self::assertSame(['id', 'computed'], $this->columnNames($filter->filter($this->fields(), new FixtureCrudControllerWithColumnNames())));
    }

    public function testAppliesTheQueryOverride(): void
    {
        $filter = $this->createFilter(new Request([ColumnSelectionResolver::QUERY_PARAM => 'heading,id']));

        self::assertSame(['heading', 'id'], $this->columnNames($filter->filter($this->fields(), new FixtureCrudControllerWithColumnNames())));
    }

    public function testLeavesControllersWithoutConfigurationUntouched(): void
    {
        $filter = $this->createFilter(new Request([ColumnSelectionResolver::QUERY_PARAM => 'id']));

        self::assertSame(['id', 'heading', 'computed'], $this->columnNames($filter->filter($this->fields(), new FixtureCrudController())));
    }

    public function testLeavesFieldsUntouchedWithoutRequest(): void
    {
        $filter = new CustomizableColumnsFieldsFilter(new RequestStack(), new ColumnSelectionResolver($this->createStub(ColumnPreferenceStorageInterface::class)));

        self::assertSame(['id', 'heading', 'computed'], $this->columnNames($filter->filter($this->fields(), new FixtureCrudControllerWithColumnNames())));
    }

    public function testWithoutFilteringReturnsEveryFieldAndTurnsTheFilterBackOn(): void
    {
        $filter = $this->createFilter(new Request());

        $all = $filter->withoutFiltering(fn (): array => $filter->filter($this->fields(), new FixtureCrudControllerWithColumnNames()));

        self::assertSame(['id', 'heading', 'computed'], $this->columnNames($all));
        self::assertSame(['id', 'computed'], $this->columnNames($filter->filter($this->fields(), new FixtureCrudControllerWithColumnNames())));
    }

    public function testWithoutFilteringTurnsTheFilterBackOnAfterAnException(): void
    {
        $filter = $this->createFilter(new Request());

        try {
            $filter->withoutFiltering(static fn (): never => throw new \RuntimeException());
        } catch (\RuntimeException) {
        }

        self::assertSame(['id', 'computed'], $this->columnNames($filter->filter($this->fields(), new FixtureCrudControllerWithColumnNames())));
    }

    private function createFilter(Request $request): CustomizableColumnsFieldsFilter
    {
        $requestStack = new RequestStack();
        $requestStack->push($request);

        return new CustomizableColumnsFieldsFilter($requestStack, new ColumnSelectionResolver($this->createStub(ColumnPreferenceStorageInterface::class)));
    }

    /**
     * @return list<FieldInterface>
     */
    private function fields(): array
    {
        return [
            IdField::new('id'),
            TextField::new('title')->setCustomOption(ColumnName::OPTION, 'heading'),
            TextField::new('virtual_computed')->setCustomOption(ColumnName::OPTION, 'computed'),
        ];
    }

    /**
     * @param list<FieldInterface|string> $fields
     *
     * @return list<string>
     */
    private function columnNames(array $fields): array
    {
        return array_map(static fn (FieldInterface|string $field): string => \is_string($field) ? $field : ColumnName::of($field->getAsDto()), $fields);
    }
}
