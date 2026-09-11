<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\CrudExport;

/**
 * Forces sync or async execution for a controller's exports, overriding the row-count-based
 * decision (`async_threshold`). `max_limit` still applies as a hard rejection ceiling
 * regardless of the forced mode.
 */
enum CrudExportMode
{
    case Sync;
    case Async;
}
