<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\Pagination\EasyAdmin;

use CoopTilleuls\Bundle\EAExtensionsBundle\Pagination\AvailablePageSizesProviderInterface;
use CoopTilleuls\Bundle\EAExtensionsBundle\Pagination\DefaultPageSizeProviderInterface;
use CoopTilleuls\Bundle\EAExtensionsBundle\Pagination\PageSizeResolver;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Dto\PaginatorDto;
use EasyCorp\Bundle\EasyAdminBundle\Event\BeforeCrudActionEvent;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ControllerEvent;

/**
 * Resolves and applies the effective page size before EasyAdmin builds its paginator.
 * BeforeCrudActionEvent fires inside AbstractCrudController::index(), right before
 * PaginatorFactory::create() reads CrudDto::getPaginator() — the last point at which the
 * page size can still be changed for the current request.
 *
 * AbstractCrudController::autocomplete() dispatches no BeforeCrudActionEvent, so it's handled
 * on kernel.controller instead, once EasyAdmin's AdminRouterSubscriber (priority 128) has
 * stored the AdminContext on the request.
 */
#[AsEventListener(event: BeforeCrudActionEvent::class)]
#[AsEventListener(event: ControllerEvent::class, method: 'onAutocomplete')]
final class PaginationCrudListener
{
    public const REQUEST_ATTRIBUTE_AVAILABLE_PAGE_SIZES = '_ea_extensions_pagination_available_page_sizes';

    private const ACTION_AUTOCOMPLETE = 'autocomplete';

    /**
     * @param list<int> $defaultAvailablePageSizes
     */
    public function __construct(
        private readonly PageSizeResolver $resolver,
        private readonly array $defaultAvailablePageSizes,
        #[Autowire(param: 'coop_tilleuls_ea_extensions.pagination.autocomplete_page_size')]
        private readonly ?int $autocompletePageSize = null,
    ) {
    }

    public function __invoke(BeforeCrudActionEvent $event): void
    {
        $context = $event->getAdminContext();
        if (null === $context || Crud::PAGE_INDEX !== $context->getCrud()?->getCurrentPage()) {
            return;
        }

        $crudControllerFqcn = $context->getCrud()->getControllerFqcn();

        $controllerDefault = null;
        if (null !== $crudControllerFqcn && is_subclass_of($crudControllerFqcn, DefaultPageSizeProviderInterface::class)) {
            $controllerDefault = $crudControllerFqcn::getDefaultPageSize($context);
        }

        $availablePageSizes = null;
        if (null !== $crudControllerFqcn && is_subclass_of($crudControllerFqcn, AvailablePageSizesProviderInterface::class)) {
            $availablePageSizes = $crudControllerFqcn::getAvailablePageSizes($context);
        }

        // A null availablePageSizes falls back to the bundle-wide list at render time (see the
        // request attribute set below), so the consistency check must compare against that same
        // effective list — not skip just because this controller didn't declare its own, which
        // would let e.g. getDefaultPageSize() => 15 through unnoticed while the bundle-wide list
        // stays [10, 25, 50, 100].
        $effectiveAvailablePageSizes = $availablePageSizes ?? $this->defaultAvailablePageSizes;

        if (null !== $controllerDefault && [] !== $effectiveAvailablePageSizes && !\in_array($controllerDefault, $effectiveAvailablePageSizes, true)) {
            throw new \LogicException(sprintf('%s::getDefaultPageSize() returned %d, which is not one of the available page sizes (%s).', $crudControllerFqcn, $controllerDefault, implode(', ', $effectiveAvailablePageSizes)));
        }

        $this->setPageSize($context, $this->resolver->resolve($context->getRequest(), $controllerDefault, $availablePageSizes));

        $context->getRequest()->attributes->set(
            self::REQUEST_ATTRIBUTE_AVAILABLE_PAGE_SIZES,
            $availablePageSizes ?? $this->defaultAvailablePageSizes,
        );
    }

    /**
     * Applies the controller's own default, else "pagination.autocomplete_page_size": no
     * selector is rendered for an autocomplete, so the available-sizes list (and its
     * consistency check) doesn't apply. When neither is set, EasyAdmin's own paginator is left
     * untouched rather than switching every autocomplete to the bundle-wide default.
     */
    public function onAutocomplete(ControllerEvent $event): void
    {
        $context = $event->getRequest()->attributes->get(EA::CONTEXT_REQUEST_ATTRIBUTE);
        if (!$context instanceof AdminContext || self::ACTION_AUTOCOMPLETE !== $context->getCrud()?->getCurrentAction()) {
            return;
        }

        $crudControllerFqcn = $context->getCrud()->getControllerFqcn();

        $pageSize = null;
        if (null !== $crudControllerFqcn && is_subclass_of($crudControllerFqcn, DefaultPageSizeProviderInterface::class)) {
            $pageSize = $crudControllerFqcn::getDefaultPageSize($context);
        }

        $pageSize ??= $this->autocompletePageSize;
        if (null === $pageSize) {
            return;
        }

        // An empty list locks the page size: the query-string override is ignored and only
        // max_page_size clamping applies.
        $this->setPageSize($context, $this->resolver->resolve($context->getRequest(), $pageSize, []));
    }

    /**
     * @param AdminContext<object> $context
     */
    private function setPageSize(AdminContext $context, int $pageSize): void
    {
        $crud = $context->getCrud();
        if (null === $crud) {
            return;
        }

        $existingPaginator = $crud->getPaginator();
        $crud->setPaginator(new PaginatorDto(
            $pageSize,
            $existingPaginator->getRangeSize(),
            $existingPaginator->getRangeEdgeSize(),
            $existingPaginator->fetchJoinCollection(),
            $existingPaginator->useOutputWalkers(),
        ));
    }
}
