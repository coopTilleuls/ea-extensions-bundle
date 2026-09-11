<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Unit\CrudExport;

use CoopTilleuls\Bundle\EAExtensionsBundle\CrudExport\CrudExportColumn;
use CoopTilleuls\Bundle\EAExtensionsBundle\CrudExport\CrudExportFieldResolverInterface;
use CoopTilleuls\Bundle\EAExtensionsBundle\CrudExport\Value\CrudExportValueResolver;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Contracts\Service\ServiceProviderInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class ValueResolverFixtureRelation
{
    public function __construct(public string $label = 'related-label')
    {
    }

    public function __toString(): string
    {
        return $this->label;
    }
}

final class ValueResolverFixtureEntity
{
    private string $secret = 'hidden';

    /**
     * @param list<string>                                        $tags
     * @param ?ArrayCollection<int, ValueResolverFixtureRelation> $items
     */
    public function __construct(
        public string $name = 'entity-name',
        public ?ValueResolverFixtureRelation $relation = null,
        public ?\DateTimeImmutable $createdAt = null,
        public array $tags = ['a', 'b'],
        public ?ArrayCollection $items = null,
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function describe(): string
    {
        return $this->name.':'.$this->secret;
    }
}

final class CrudExportValueResolverTest extends TestCase
{
    private function createResolver(): CrudExportValueResolver
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        return new CrudExportValueResolver($this->createStub(ServiceProviderInterface::class), $translator);
    }

    public function testResolveValueUsesGetter(): void
    {
        $entity = new ValueResolverFixtureEntity(name: 'hello');
        $column = new CrudExportColumn('name', 'Name');

        self::assertSame('hello', $this->createResolver()->resolveValue($entity, $column));
    }

    public function testResolveValueTraversesRelationDotPath(): void
    {
        $entity = new ValueResolverFixtureEntity(relation: new ValueResolverFixtureRelation('nested'));
        $column = new CrudExportColumn('relation.label', 'Relation');

        self::assertSame('nested', $this->createResolver()->resolveValue($entity, $column));
    }

    public function testResolveValueReturnsNullWhenRelationIsNull(): void
    {
        $entity = new ValueResolverFixtureEntity();
        $column = new CrudExportColumn('relation.label', 'Relation');

        self::assertNull($this->createResolver()->resolveValue($entity, $column));
    }

    public function testResolveValueFormatsDateTime(): void
    {
        $entity = new ValueResolverFixtureEntity(createdAt: new \DateTimeImmutable('2026-01-02 03:04:05'));
        $column = new CrudExportColumn('createdAt', 'Created');

        self::assertSame('02/01/2026 03:04:05', $this->createResolver()->resolveValue($entity, $column));
    }

    public function testResolveValueImplodesArray(): void
    {
        $entity = new ValueResolverFixtureEntity(tags: ['a', 'b', 'c']);
        $column = new CrudExportColumn('tags', 'Tags');

        self::assertSame('a, b, c', $this->createResolver()->resolveValue($entity, $column));
    }

    public function testResolveValueImplodesCollection(): void
    {
        $entity = new ValueResolverFixtureEntity(items: new ArrayCollection([
            new ValueResolverFixtureRelation('x'),
            new ValueResolverFixtureRelation('y'),
        ]));
        $column = new CrudExportColumn('items', 'Items');

        self::assertSame('x, y', $this->createResolver()->resolveValue($entity, $column));
    }

    public function testResolveValueReturnsNullForAPrivatePropertyWithoutAGetter(): void
    {
        // Regression test: property_exists() returns true for private/protected properties
        // too, even from outside the declaring class — reading them directly used to fatal
        // ("Cannot access private property") instead of falling through like a genuinely
        // missing property does.
        $entity = new ValueResolverFixtureEntity();
        $column = new CrudExportColumn('secret', 'Secret');

        self::assertNull($this->createResolver()->resolveValue($entity, $column));
    }

    public function testResolveValueDelegatesToCustomResolver(): void
    {
        $entity = new ValueResolverFixtureEntity();
        $column = new CrudExportColumn('name', 'Name', resolverFqcn: 'App\\Some\\Resolver');

        $customResolver = new class implements CrudExportFieldResolverInterface {
            public function __invoke(object $entity): mixed
            {
                return 'custom-value';
            }
        };

        $locator = $this->createMock(ServiceProviderInterface::class);
        $locator->method('get')->with('App\\Some\\Resolver')->willReturn($customResolver);
        $translator = $this->createStub(TranslatorInterface::class);

        $resolver = new CrudExportValueResolver($locator, $translator);

        self::assertSame('custom-value', $resolver->resolveValue($entity, $column));
    }

    public function testResolveHeaderFallsBackToPropertyNameWhenLabelIsFalseOrEmpty(): void
    {
        $column = new CrudExportColumn('propertyName', false);

        self::assertSame('propertyName', $this->createResolver()->resolveHeader($column));
    }

    public function testResolveHeaderTranslatesAPlainStringLabel(): void
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->expects(self::once())->method('trans')->with('field.isbn')->willReturn('ISBN');

        $resolver = new CrudExportValueResolver($this->createStub(ServiceProviderInterface::class), $translator);
        $column = new CrudExportColumn('isbn', 'field.isbn');

        self::assertSame('ISBN', $resolver->resolveHeader($column));
    }

    public function testResolveHeaderTranslatesATranslatableLabelExactlyOnce(): void
    {
        // Regression test: a TranslatableInterface label used to fall through into the
        // plain-string branch too (its ->trans() result is a string), running the
        // already-translated text back through the translator a second time — harmless
        // only by coincidence when the translated text isn't itself a catalog key.
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->expects(self::once())->method('trans')->with('field.isbn')->willReturn('ISBN');

        $resolver = new CrudExportValueResolver($this->createStub(ServiceProviderInterface::class), $translator);
        $column = new CrudExportColumn('isbn', new TranslatableMessage('field.isbn'));

        self::assertSame('ISBN', $resolver->resolveHeader($column));
    }
}
