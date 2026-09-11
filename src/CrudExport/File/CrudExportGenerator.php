<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\CrudExport\File;

use CoopTilleuls\Bundle\EAExtensionsBundle\CrudExport\CrudExportColumn;
use CoopTilleuls\Bundle\EAExtensionsBundle\CrudExport\Value\CrudExportValueResolver;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;

/**
 * Orchestrates value resolution + file writing for one export. Does not dispatch
 * CrudExportFileReadyEvent itself — the caller (CrudExportSubscriber for sync exports,
 * ExportCrudMessageHandler for async ones) does, since only it knows whether a download
 * URL makes sense (async) and the async/sync flag to put on the event.
 */
final readonly class CrudExportGenerator
{
    private const int ENTITY_MANAGER_CLEAR_BATCH_SIZE = 5000;

    public function __construct(
        private CrudExportValueResolver $valueResolver,
        private CrudExportFileWriter $fileWriter,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param Query<null, mixed>     $query
     * @param class-string           $crudControllerFqcn
     * @param list<CrudExportColumn> $columns
     *
     * @return array{storagePath: string, filename: string}
     */
    public function generate(
        Query $query,
        array $columns,
        string $crudControllerFqcn,
        string $requesterId,
        string $exportName,
        string $format,
    ): array {
        $headers = array_map($this->valueResolver->resolveHeader(...), $columns);
        $rows = $this->resolveRows($query, $columns);

        return $this->fileWriter->write($headers, $rows, $requesterId, $exportName, $format, $crudControllerFqcn);
    }

    /**
     * @param Query<null, mixed>     $query
     * @param list<CrudExportColumn> $columns
     *
     * @return \Generator<list<mixed>>
     */
    private function resolveRows(Query $query, array $columns): \Generator
    {
        $query->setHint(Query::HINT_READ_ONLY, true);
        $processedRows = 0;

        try {
            foreach ($query->toIterable() as $entity) {
                yield array_map(fn (CrudExportColumn $column): mixed => $this->valueResolver->resolveValue($entity, $column), $columns);

                if (0 === ++$processedRows % self::ENTITY_MANAGER_CLEAR_BATCH_SIZE) {
                    $this->clearEntityManager();
                }
            }
        } finally {
            $this->clearEntityManager();
        }
    }

    private function clearEntityManager(): void
    {
        $this->entityManager->clear();
        gc_collect_cycles();
    }
}
