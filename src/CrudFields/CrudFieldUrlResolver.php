<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\CrudFields;

use CoopTilleuls\Bundle\EAExtensionsBundle\CustomizableColumns\ColumnSelectionResolver;

/**
 * Resolves the embed's fetch URL and "voir tout" URL. Consolidated here (rather than split
 * across a Twig filter and the field configurator) specifically so the "voir tout" URL always
 * stays consistent with whatever filter the fetch URL applies — see resolveSeeAllUrl().
 */
final class CrudFieldUrlResolver
{
    /**
     * @param list<string> $crudColumns
     */
    public function resolveFetchUrl(string $baseUrl, bool $hideGlobalActions, bool $hideEntityActions, bool $showFilters, array $crudColumns = []): string
    {
        $params = [CrudField::QUERY_PARAM_CLEAN_LAYOUT => '1'];

        if ($hideGlobalActions) {
            $params[CrudField::QUERY_PARAM_HIDE_GLOBAL_ACTIONS] = '1';
        }

        if ($hideEntityActions) {
            $params[CrudField::QUERY_PARAM_HIDE_ENTITY_ACTIONS] = '1';
        }

        if ([] !== $crudColumns) {
            $params[ColumnSelectionResolver::QUERY_PARAM] = implode(',', $crudColumns);
        }

        if ($showFilters) {
            $params[CrudField::QUERY_PARAM_SHOW_FILTERS] = '1';

            // Filters already baked into $baseUrl (e.g. via overrideIndexUrl()) are "forced":
            // the user didn't apply them and shouldn't be able to remove them via the embed's
            // own filter UI, so they're excluded from the "applied filters" count/reset link.
            parse_str((string) parse_url($baseUrl, PHP_URL_QUERY), $existingParams);
            if (!empty($existingParams['filters']) && is_array($existingParams['filters'])) {
                $params[CrudField::QUERY_PARAM_HIDDEN_FILTERS] = array_keys($existingParams['filters']);
            }
        }

        return self::appendQuery($baseUrl, $params);
    }

    /**
     * Defaults "voir tout" to whatever overrideIndexUrl() resolved to (so it reflects the same
     * business filter as the embed's fetch), falling back to the bare unfiltered index only
     * when overrideIndexUrl() was never called. setSeeAllUrl() always takes precedence, for the
     * cases where the two genuinely should differ.
     */
    public function resolveSeeAllUrl(?string $explicitSeeAllUrl, ?string $overrideIndexUrl, string $bareIndexUrl): string
    {
        return $explicitSeeAllUrl ?? $overrideIndexUrl ?? $bareIndexUrl;
    }

    /**
     * @param array<string, string|list<string>> $params
     */
    private static function appendQuery(string $url, array $params): string
    {
        $separator = str_contains($url, '?') ? '&' : '?';

        return $url.$separator.http_build_query($params);
    }
}
