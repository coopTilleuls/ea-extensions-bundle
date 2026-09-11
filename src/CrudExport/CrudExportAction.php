<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\CrudExport;

final class CrudExportAction
{
    public const string ACTION_NAME = 'crudExport';

    /**
     * Query parameter appended to the current index URL to trigger an export of the
     * page currently being viewed (same filters/sort/search/columns).
     */
    public const string QUERY_PARAM_TRIGGER = 'crudExport';
}
