<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\Pagination;

use Symfony\Component\HttpFoundation\Request;

/**
 * Resolves the effective page size for a request: query-string override (validated, ignored
 * when the controller declares a locked/empty page-size list) > per-controller default >
 * bundle-wide default, then clamped to "pagination.max_page_size" when configured.
 */
final class PageSizeResolver
{
    public function __construct(
        private readonly string $queryParameter,
        private readonly int $defaultPageSize,
        private readonly ?int $maxPageSize,
    ) {
    }

    /**
     * @param list<int>|null $availablePageSizes the controller's own list (null = bundle
     *                                           default list applies, [] = locked)
     */
    public function resolve(Request $request, ?int $controllerDefault, ?array $availablePageSizes): int
    {
        $fallback = $this->clamp($controllerDefault ?? $this->defaultPageSize);

        if ([] === $availablePageSizes) {
            return $fallback;
        }

        // Read via all() rather than InputBag::get(), which throws a BadRequestException for
        // a non-scalar value (e.g. "?itemsPerPage[]=5") instead of the graceful fallback every
        // other malformed value (negative, zero, non-numeric) gets below.
        $raw = $request->query->all()[$this->queryParameter] ?? null;
        if (!\is_scalar($raw)) {
            return $fallback;
        }

        $requested = filter_var($raw, \FILTER_VALIDATE_INT);
        if (false === $requested || $requested < 1) {
            return $fallback;
        }

        return $this->clamp($requested);
    }

    private function clamp(int $pageSize): int
    {
        $pageSize = max(1, $pageSize);

        if (null !== $this->maxPageSize && $pageSize > $this->maxPageSize) {
            return $this->maxPageSize;
        }

        return $pageSize;
    }
}
