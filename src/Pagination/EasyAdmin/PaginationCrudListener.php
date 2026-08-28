<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\Pagination\EasyAdmin;

use CoopTilleuls\Bundle\EAExtensionsBundle\Pagination\PageSizeResolver;
use CoopTilleuls\Bundle\EAExtensionsBundle\Pagination\PaginationConfigurationInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Dto\PaginatorDto;
use EasyCorp\Bundle\EasyAdminBundle\Event\BeforeCrudActionEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Resolves and applies the effective page size before EasyAdmin builds its paginator.
 * BeforeCrudActionEvent fires inside AbstractCrudController::index(), right before
 * PaginatorFactory::create() reads CrudDto::getPaginator() — the last point at which the
 * page size can still be changed for the current request.
 */
#[AsEventListener(event: BeforeCrudActionEvent::class)]
final class PaginationCrudListener
{
    public const REQUEST_ATTRIBUTE_AVAILABLE_PAGE_SIZES = '_ea_extensions_pagination_available_page_sizes';

    /**
     * @param list<int> $defaultAvailablePageSizes
     */
    public function __construct(
        private readonly PageSizeResolver $resolver,
        private readonly array $defaultAvailablePageSizes,
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
        $availablePageSizes = null;
        if (null !== $crudControllerFqcn && is_subclass_of($crudControllerFqcn, PaginationConfigurationInterface::class)) {
            $controllerDefault = $crudControllerFqcn::getDefaultPageSize($context);
            $availablePageSizes = $crudControllerFqcn::getAvailablePageSizes($context);
        }

        $pageSize = $this->resolver->resolve($context->getRequest(), $controllerDefault, $availablePageSizes);

        $existingPaginator = $context->getCrud()->getPaginator();
        $context->getCrud()->setPaginator(new PaginatorDto(
            $pageSize,
            $existingPaginator->getRangeSize(),
            $existingPaginator->getRangeEdgeSize(),
            $existingPaginator->fetchJoinCollection(),
            $existingPaginator->useOutputWalkers(),
        ));

        $context->getRequest()->attributes->set(
            self::REQUEST_ATTRIBUTE_AVAILABLE_PAGE_SIZES,
            $availablePageSizes ?? $this->defaultAvailablePageSizes,
        );
    }
}
