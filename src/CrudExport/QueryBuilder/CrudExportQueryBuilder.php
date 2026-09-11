<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\CrudExport\QueryBuilder;

use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Controller\CrudControllerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Factory\FilterFactory;
use Psr\Log\LoggerInterface;

/**
 * Rebuilds the same (unpaginated) query the index page uses — via the resolved
 * controller's own createIndexQueryBuilder()/configureFields(), so custom overrides
 * are honored exactly like the normal grid — plus a row count for the sync/async decision.
 */
final readonly class CrudExportQueryBuilder
{
    public function __construct(
        private FilterFactory $filterFactory,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @param AdminContext<object>            $context
     * @param CrudControllerInterface<object> $controller
     *
     * @return array{0: Query<null, mixed>, 1: int}
     */
    public function build(AdminContext $context, CrudControllerInterface $controller): array
    {
        $fields = new FieldCollection($controller->configureFields(Crud::PAGE_INDEX));
        $filterConfig = $context->getCrud()?->getFiltersConfig() ?? throw new \RuntimeException('Unable to resolve the current CRUD filters configuration.');
        $filters = $this->filterFactory->create($filterConfig, $fields, $context->getEntity());

        $searchDto = $context->getSearch() ?? throw new \RuntimeException('Unable to resolve the current CRUD search context.');
        $qb = $controller->createIndexQueryBuilder($searchDto, $context->getEntity(), $fields, $filters);
        $qb->resetDQLPart('orderBy');

        $alias = $qb->getRootAliases()[0] ?? throw new \RuntimeException('Unable to determine the query builder root alias.');

        $count = $this->count(clone $qb, $alias);

        return [$qb->distinct()->getQuery(), $count];
    }

    private function count(QueryBuilder $qb, string $alias): int
    {
        $qb->resetDQLPart('groupBy')->select(sprintf('COUNT(DISTINCT %s.id)', $alias));

        try {
            return (int) $qb->getQuery()->getSingleScalarResult();
        } catch (\Throwable $primaryException) {
            try {
                $qb->select(sprintf('COUNT(DISTINCT %s)', $alias));

                return (int) $qb->getQuery()->getSingleScalarResult();
            } catch (\Throwable $fallbackException) {
                $this->logger->error('CrudExport: unable to count the rows to export.', [
                    'primaryException' => $primaryException,
                    'fallbackException' => $fallbackException,
                ]);

                throw new \RuntimeException('Unable to count the rows to export.', 0, $fallbackException);
            }
        }
    }
}
