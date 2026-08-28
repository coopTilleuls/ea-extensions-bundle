<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\CustomizableColumns\Controller;

use CoopTilleuls\Bundle\EAExtensionsBundle\CustomizableColumns\AvailableColumnsResolver;
use CoopTilleuls\Bundle\EAExtensionsBundle\CustomizableColumns\ColumnSelectionResolver;
use CoopTilleuls\Bundle\EAExtensionsBundle\CustomizableColumns\CustomizableColumnsConfigurationInterface;
use CoopTilleuls\Bundle\EAExtensionsBundle\CustomizableColumns\Form\ColumnPreferenceType;
use CoopTilleuls\Bundle\EAExtensionsBundle\CustomizableColumns\Storage\ColumnPreferenceStorageInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

final class ColumnPreferenceController
{
    public function __construct(
        private readonly AvailableColumnsResolver $availableColumnsResolver,
        private readonly ColumnSelectionResolver $columnSelectionResolver,
        private readonly ColumnPreferenceStorageInterface $storage,
        private readonly FormFactoryInterface $formFactory,
        private readonly Environment $twig,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    #[Route('/_ea-extensions/customizable-columns/render', name: 'ea_extensions_customizable_columns_render', methods: ['GET'])]
    public function render(Request $request): Response
    {
        $crudControllerFqcn = $this->getCrudControllerFqcn($request);
        $availableFields = $this->availableColumnsResolver->resolve($crudControllerFqcn, $request);
        $availableProperties = array_keys($availableFields);

        [$lockedColumns, $defaultColumns] = $this->getConfiguration($crudControllerFqcn);

        $selectedColumns = $this->columnSelectionResolver->resolve(
            $request,
            $crudControllerFqcn,
            $request->query->getString(ColumnSelectionResolver::QUERY_PARAM),
            $availableProperties,
            [] === $defaultColumns ? $availableProperties : $defaultColumns,
            $lockedColumns,
        );

        $pickableProperties = array_values(array_diff($availableProperties, $lockedColumns));
        $orderedPickable = array_merge(
            array_values(array_intersect($selectedColumns, $pickableProperties)),
            array_values(array_diff($pickableProperties, $selectedColumns)),
        );

        $columns = [];
        foreach ($orderedPickable as $property) {
            $columns[$property] = $availableFields[$property]->getLabel();
        }

        $redirectUrl = $request->query->getString('redirectUrl');

        $form = $this->formFactory->createNamed('columns', ColumnPreferenceType::class, null, [
            'action' => $this->urlGenerator->generate('ea_extensions_customizable_columns_save', [
                'crudControllerFqcn' => $crudControllerFqcn,
                'redirectUrl' => $redirectUrl,
            ]),
            'method' => 'POST',
            'columns' => $columns,
            'checked_columns' => $selectedColumns,
        ]);

        return new Response($this->twig->render('@EAExtensions/customizable_columns/render.html.twig', [
            'form' => $form->createView(),
            'crud_controller_fqcn' => $crudControllerFqcn,
        ]));
    }

    #[Route('/_ea-extensions/customizable-columns/save', name: 'ea_extensions_customizable_columns_save', methods: ['POST'])]
    public function save(Request $request): RedirectResponse
    {
        $crudControllerFqcn = $this->getCrudControllerFqcn($request);

        [$lockedColumns] = $this->getConfiguration($crudControllerFqcn);
        $availableProperties = array_keys($this->availableColumnsResolver->resolve($crudControllerFqcn, $request));

        $submittedFields = $request->request->all('columns');
        $save = '1' === ($submittedFields['save'] ?? null);
        unset($submittedFields['save']);

        $submittedColumns = array_keys($submittedFields);
        $columns = array_values(array_intersect(array_unique(array_merge($lockedColumns, $submittedColumns)), $availableProperties));

        if ($save) {
            $this->storage->saveColumns($request, $crudControllerFqcn, $columns);
        }

        $redirectUrl = $request->query->getString('redirectUrl');
        if ('' === $redirectUrl || !$this->isSameOrigin($request, $redirectUrl)) {
            throw new NotFoundHttpException('Missing or invalid redirect URL.');
        }

        return new RedirectResponse($this->appendColumnsQueryParam($redirectUrl, $columns));
    }

    private function isSameOrigin(Request $request, string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);

        return null === $host || $host === $request->getHost();
    }

    /**
     * Merges the resolved columns into the redirect URL's query string, leaving the rest of the
     * URL (scheme, host, port, path) untouched — rebuilding it from parse_url() parts would drop
     * the port instead.
     *
     * @param list<string> $columns
     */
    private function appendColumnsQueryParam(string $redirectUrl, array $columns): string
    {
        if ([] === $columns) {
            return $redirectUrl;
        }

        $fragment = '';
        if (false !== $hashPosition = strpos($redirectUrl, '#')) {
            $fragment = substr($redirectUrl, $hashPosition);
            $redirectUrl = substr($redirectUrl, 0, $hashPosition);
        }

        [$beforeQuery, $existingQuery] = 2 === count($split = explode('?', $redirectUrl, 2)) ? $split : [$split[0], ''];
        parse_str($existingQuery, $query);
        $query[ColumnSelectionResolver::QUERY_PARAM] = implode(',', $columns);

        return $beforeQuery.'?'.http_build_query($query).$fragment;
    }

    private function getCrudControllerFqcn(Request $request): string
    {
        $crudControllerFqcn = $request->query->getString('crudControllerFqcn');
        if ('' === $crudControllerFqcn || !class_exists($crudControllerFqcn)) {
            throw new NotFoundHttpException('Missing or invalid "crudControllerFqcn" parameter.');
        }

        return $crudControllerFqcn;
    }

    /**
     * @return array{0: list<string>, 1: list<string>}
     */
    private function getConfiguration(string $crudControllerFqcn): array
    {
        if (is_subclass_of($crudControllerFqcn, CustomizableColumnsConfigurationInterface::class)) {
            return [$crudControllerFqcn::getLockedColumns(), $crudControllerFqcn::getDefaultColumns()];
        }

        return [[], []];
    }
}
