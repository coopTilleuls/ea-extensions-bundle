<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\EasyAdmin;

use CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\Entity\Notification;
use CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\Recipient\NotificationRecipientResolverInterface;
use CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\Repository\NotificationRepository;
use CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\Security\NotificationVoter;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Ready to use out of the box — no subclassing required. Add it to your Dashboard's menu with
 * `MenuItem::linkToCrud(...)->setController(NotificationCrudController::class)`. To customize
 * beyond what {@see NotificationRecipientResolverInterface} and template overrides allow (see
 * docs/notifications.md), write your own CRUD controller reusing this bundle's repository,
 * manager, filter, and voter as plain services instead of extending this class.
 *
 * @extends AbstractCrudController<Notification>
 */
final class NotificationCrudController extends AbstractCrudController
{
    public const ACTION_TREATMENT = 'treatment.pending';
    public const ACTION_READ = 'read';

    public function __construct(
        private readonly AdminUrlGeneratorInterface $adminUrlGenerator,
        private readonly RequestStack $requestStack,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
        private readonly NotificationRepository $notificationRepository,
        private readonly NotificationRecipientResolverInterface $recipientResolver,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Notification::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        // Captured before overriding "crud/index" below, so notifications/crud/index.html.twig
        // can still extend whichever crud/index would otherwise have been used (vendor's, or a
        // theme's) — see NotificationIndexTemplateExtension.
        $this->requestStack->getCurrentRequest()?->attributes->set(
            NotificationIndexTemplateExtension::REQUEST_ATTRIBUTE_PARENT_INDEX_TEMPLATE,
            $crud->getAsDto()->getOverriddenTemplates()['crud/index'] ?? '@EasyAdmin/crud/index.html.twig',
        );

        return $crud
            ->setEntityLabelInSingular('Notification')
            ->setEntityLabelInPlural('Notifications')
            ->setPageTitle(Crud::PAGE_INDEX, 'Mes notifications')
            ->setPageTitle(Crud::PAGE_DETAIL, 'Notification')
            ->setDefaultSort(['date' => 'DESC'])
            ->overrideTemplate('crud/index', '@EAExtensions/notifications/crud/index.html.twig')
            ->overrideTemplate('crud/detail', '@EAExtensions/notifications/crud/detail.html.twig')
        ;
    }

    public function configureActions(Actions $actions): Actions
    {
        $actions = parent::configureActions($actions);

        $actions->disable(Action::NEW, Action::BATCH_DELETE);

        $entityId = $this->requestStack->getCurrentRequest()?->attributes->get('entityId');

        $readAction = Action::new(self::ACTION_READ, 'Lu')
            ->linkToUrl($entityId ? $this->adminUrlGenerator
                ->setController(self::class)
                ->setAction(Action::EDIT)
                ->set('fieldName', self::ACTION_READ)
                ->set('csrfToken', $this->csrfTokenManager->getToken(BooleanField::CSRF_TOKEN_NAME))
                ->set('entityId', $entityId)
                ->generateUrl() : '/')
            ->setHtmlAttributes([
                'data-label-true' => 'notifications.status.read',
                'data-label-false' => 'notifications.status.unread',
            ])
            ->setTemplatePath('@EAExtensions/notifications/actions/notification_status.html.twig');

        $treatmentAction = Action::new(self::ACTION_TREATMENT, 'Traitement')
            ->linkToUrl($entityId ? $this->adminUrlGenerator
                ->setController(self::class)
                ->setAction(Action::EDIT)
                ->set('fieldName', self::ACTION_TREATMENT)
                ->set('csrfToken', $this->csrfTokenManager->getToken(BooleanField::CSRF_TOKEN_NAME))
                ->set('entityId', $entityId)
                ->generateUrl() : '/')
            ->setHtmlAttributes([
                'data-label-false' => 'notifications.status.treatment_done',
                'data-label-true' => 'notifications.status.treatment_pending',
            ])
            ->setTemplatePath('@EAExtensions/notifications/actions/notification_status.html.twig');

        $order = [Action::DELETE];

        if ($entityId) {
            $notification = $this->notificationRepository->find($entityId);
            if (null !== $notification?->getTreatment()) {
                $actions->add(Crud::PAGE_DETAIL, $treatmentAction);
                $order[] = self::ACTION_TREATMENT;
            }
        }
        $actions->add(Crud::PAGE_DETAIL, $readAction);
        $order[] = self::ACTION_READ;

        // kept available (for the status toggle switches) but not rendered as a normal action
        $actions->update(Crud::PAGE_INDEX, Action::EDIT, fn (Action $action): Action => $action->displayIf(fn (): false => false));
        $actions->update(Crud::PAGE_DETAIL, Action::EDIT, fn (Action $action): Action => $action->displayIf(fn (): false => false));
        $actions->update(
            Crud::PAGE_INDEX,
            Action::DELETE,
            fn (Action $action): Action => $action->displayIf(fn (Notification $entity): bool => null === $entity->getTreatment() || false === $entity->getTreatment()->isPending()),
        );
        $actions->update(
            Crud::PAGE_DETAIL,
            Action::DELETE,
            fn (Action $action): Action => $action->displayIf(fn (Notification $entity): bool => null === $entity->getTreatment() || false === $entity->getTreatment()->isPending()),
        );

        $actions->reorder(Crud::PAGE_DETAIL, $order);

        return $actions->setPermissions([
            Action::DETAIL => NotificationVoter::IS_RELATED,
            Action::EDIT => NotificationVoter::IS_RELATED,
            Action::DELETE => NotificationVoter::IS_RELATED,
            self::ACTION_READ => NotificationVoter::IS_RELATED,
            self::ACTION_TREATMENT => new Expression('
                is_granted("'.NotificationVoter::IS_RELATED.'", object)
                and is_granted("'.NotificationVoter::IS_TREATABLE.'", object)
            '),
        ]);
    }

    public function configureFilters(Filters $filters): Filters
    {
        $filters = parent::configureFilters($filters);

        return $filters
            ->add(DateTimeFilter::new('date', 'Date'))
            ->add(TextFilter::new('type', 'Type'))
            ->add(TextFilter::new('title', 'Titre'))
            ->add(BooleanFilter::new(self::ACTION_READ, 'Statut')->setFormTypeOption('choices', [
                'Lu' => true,
                'Non lu' => false,
            ]))
            ->add(NotificationTreatmentFilter::new('Traitement'))
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        yield DateTimeField::new('date', 'Date')
            ->setFormat("dd/MM/yyyy - HH'h'mm");
        yield TextField::new('type', 'Type');
        yield TextField::new('title', 'Titre');
        yield BooleanField::new(self::ACTION_READ, 'Statut')
            ->setTextAlign('left')
            ->setFormTypeOption('row_attr', [
                'custom-labels' => [
                    'false' => 'notifications.status.unread',
                    'true' => 'notifications.status.read',
                ],
            ])
            ->setTemplatePath('@EAExtensions/notifications/fields/notification_status.html.twig');
        yield BooleanField::new(self::ACTION_TREATMENT, 'Traitement')
            ->setTextAlign('left')
            ->setFormTypeOption('row_attr', [
                'custom-labels' => [
                    'false' => 'notifications.status.treatment_done',
                    'true' => 'notifications.status.treatment_pending',
                ],
            ])
            ->setTemplatePath('@EAExtensions/notifications/fields/notification_status.html.twig');
    }

    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        $qb = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);

        return $this->notificationRepository->forRecipients($qb, $this->recipientResolver->resolveCurrentRecipients());
    }

    /**
     * @param Notification $entityInstance
     */
    public function deleteEntity(EntityManagerInterface $entityManager, object $entityInstance): void
    {
        $treatment = $entityInstance->getTreatment();
        parent::deleteEntity($entityManager, $entityInstance);
        if (null === $treatment) {
            return;
        }

        $entityManager->refresh($treatment);
        if (!$treatment->isPending() && $treatment->getNotifications()->isEmpty()) {
            $entityManager->remove($treatment);
            $entityManager->flush();
        }
    }

    #[AdminRoute('/unread-count', name: 'unread_count')]
    public function unreadCount(): Response
    {
        return new Response((string) $this->notificationRepository->getUnreadCount($this->recipientResolver->resolveCurrentRecipients()));
    }
}
