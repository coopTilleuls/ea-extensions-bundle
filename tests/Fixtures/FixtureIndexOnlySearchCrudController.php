<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Fixtures;

use CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Fixtures\Entity\FixtureEntity;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Event\BeforeCrudActionEvent;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\HttpFoundation\Response;

/**
 * Custom actions shaped like the ones a host app writes on top of the built-in index/detail/edit/new
 * pages, to cover each way the index-only-search feature can catch the page being rendered.
 *
 * @extends AbstractCrudController<FixtureEntity>
 */
final class FixtureIndexOnlySearchCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return FixtureEntity::class;
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('title');
    }

    /**
     * A custom list: only turns into an index page while running, after a first
     * BeforeCrudActionEvent already hid the search.
     *
     * @param AdminContext<FixtureEntity> $context
     */
    #[AdminRoute('/custom-list', name: 'custom_list')]
    public function customList(AdminContext $context): KeyValueStore|Response
    {
        $this->container->get('event_dispatcher')->dispatch(new BeforeCrudActionEvent($context));
        $crud = $context->getCrud();
        $crud?->setPageName(Crud::PAGE_INDEX);
        $crud?->getActionsConfig()->setPageName(Crud::PAGE_INDEX);

        return $this->index($context);
    }

    /**
     * A custom page rendered by EasyAdmin's own CrudResponseListener, with no BeforeCrudActionEvent.
     */
    #[AdminRoute('/custom-page', name: 'custom_page')]
    public function customPage(): KeyValueStore
    {
        return KeyValueStore::new(['templatePath' => '@EasyAdmin/page/content.html.twig']);
    }

    /**
     * A custom page rendering its own Response, after dispatching BeforeCrudActionEvent.
     *
     * @param AdminContext<FixtureEntity> $context
     */
    #[AdminRoute('/custom-rendered-page', name: 'custom_rendered_page')]
    public function customRenderedPage(AdminContext $context): Response
    {
        $this->container->get('event_dispatcher')->dispatch(new BeforeCrudActionEvent($context));

        return $this->render('@EasyAdmin/page/content.html.twig');
    }
}
