<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\CrudExport;

use Symfony\Contracts\Translation\TranslatableInterface;

final readonly class CrudExportColumn
{
    /**
     * @see CrudExportFieldResolverInterface
     */
    public const string OPTION_VALUE_RESOLVER = 'crudExportValueResolver';

    public function __construct(
        public string $propertyName,
        public bool|string|TranslatableInterface|null $label,
        public ?string $resolverFqcn = null,
    ) {
    }
}
