<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Unit\Notifications\EasyAdmin;

use CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\EasyAdmin\CaseInsensitiveTextFilter;
use CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\Entity\Notification;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FilterDataDto;
use PHPUnit\Framework\TestCase;

final class CaseInsensitiveTextFilterTest extends TestCase
{
    public function testItComparesBothSidesInLowerCase(): void
    {
        $queryBuilder = new QueryBuilder($this->createStub(EntityManagerInterface::class));
        $filter = CaseInsensitiveTextFilter::new('title');
        $filterData = FilterDataDto::new(0, $filter->getAsDto(), 'n', ['comparison' => 'like', 'value' => '%Export%']);

        /** @var EntityDto<object> $entityDto */
        $entityDto = new EntityDto(Notification::class, new ClassMetadata(Notification::class));
        $filter->apply($queryBuilder, $filterData, null, $entityDto);

        self::assertSame('LOWER(n.title) like LOWER(:title_0)', (string) $queryBuilder->getDQLPart('where'));
        self::assertSame('%Export%', $queryBuilder->getParameter('title_0')?->getValue());
    }
}
