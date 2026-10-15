<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\IndexOnlySearch\EasyAdmin;

use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Event\BeforeCrudActionEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ViewEvent;

/**
 * Hides the layout's top-bar search everywhere but on index (list) pages — EasyAdmin only lets
 * a CRUD enable or disable it as a whole, for all of its pages.
 *
 * The layout shows the search box whenever CrudDto::isSearchEnabled(), i.e. its search fields
 * aren't null. Those are only read by templates: the actual search query (index, autocomplete...)
 * runs off the SearchDto EasyAdmin copied them into when building the AdminContext. So nulling them
 * on a non-index page only removes the box, and the original value is kept in a request attribute
 * to be restored if the page turns out to be an index after all.
 *
 * The decision follows the current page name as late as possible, since custom actions may switch
 * it (e.g. a custom list calling CrudDto::setPageName(Crud::PAGE_INDEX)): on BeforeCrudActionEvent,
 * which covers actions rendering their own Response, and again right before EasyAdmin's own
 * CrudResponseListener renders a KeyValueStore (priority > 0).
 */
final class IndexOnlySearchListener
{
    public const REQUEST_ATTRIBUTE_SEARCH_FIELDS = '_ea_extensions_index_only_search_fields';

    #[AsEventListener(event: BeforeCrudActionEvent::class)]
    public function onBeforeCrudAction(BeforeCrudActionEvent $event): void
    {
        $context = $event->getAdminContext();
        if (null !== $context) {
            $this->apply($context);
        }
    }

    #[AsEventListener(event: ViewEvent::class, priority: 8)]
    public function onKernelView(ViewEvent $event): void
    {
        if (!$event->isMainRequest() || !$event->getControllerResult() instanceof KeyValueStore) {
            return;
        }

        $context = $event->getRequest()->attributes->get(EA::CONTEXT_REQUEST_ATTRIBUTE);
        if ($context instanceof AdminContext) {
            $this->apply($context);
        }
    }

    /**
     * @param AdminContext<object> $context
     */
    private function apply(AdminContext $context): void
    {
        $crud = $context->getCrud();
        if (null === $crud) {
            return;
        }

        $attributes = $context->getRequest()->attributes;
        $hidden = $attributes->has(self::REQUEST_ATTRIBUTE_SEARCH_FIELDS);

        if (Crud::PAGE_INDEX === $crud->getCurrentPage()) {
            if ($hidden) {
                /** @var array<string>|null $searchFields */
                $searchFields = $attributes->get(self::REQUEST_ATTRIBUTE_SEARCH_FIELDS);
                $crud->setSearchFields($searchFields);
                $attributes->remove(self::REQUEST_ATTRIBUTE_SEARCH_FIELDS);
            }

            return;
        }

        if (!$hidden) {
            $attributes->set(self::REQUEST_ATTRIBUTE_SEARCH_FIELDS, $crud->getSearchFields());
            $crud->setSearchFields(null);
        }
    }
}
