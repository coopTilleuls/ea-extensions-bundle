<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\EasyAdmin;

use CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\Entity\Notification;
use CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\Event\NotificationTreatmentChangedEvent;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Event\AfterCrudActionEvent;
use EasyCorp\Bundle\EasyAdminBundle\Event\BeforeCrudActionEvent;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * The CRUD's inline "treatment" toggle already goes through EasyAdmin's own {@see Action::EDIT}
 * action (see {@see NotificationCrudController::configureActions()}), so this listens for that
 * specific field edit rather than exposing a bundle-owned route for it.
 *
 * This relies on the `fieldName` query parameter of EasyAdmin's own AJAX inline-edit endpoint
 * (see {@see \EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController::ajaxEdit()}) —
 * there's no more targeted vendor event to hook into: {@see \EasyCorp\Bundle\EasyAdminBundle\Event\AfterEntityUpdatedEvent}
 * carries the updated entity but not which property changed. A future EasyAdmin release
 * restructuring that query parameter would silently stop this listener from firing.
 *
 * The event is only dispatched when the state really changed, so a request resending the current
 * value (a retry, a second tab still showing the old state) doesn't dispatch it again.
 */
#[AsEventListener(event: BeforeCrudActionEvent::class, method: 'rememberPendingState')]
#[AsEventListener(event: AfterCrudActionEvent::class, method: 'dispatchIfChanged')]
final class NotificationTreatmentSubscriber
{
    private const string PENDING_BEFORE_EDIT_ATTRIBUTE = '_ea_extensions_notification_treatment_pending';

    public function __construct(
        #[Autowire(param: 'coop_tilleuls_ea_extensions.notifications.crud_controller')]
        private readonly string $crudControllerFqcn,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function rememberPendingState(BeforeCrudActionEvent $event): void
    {
        $context = $event->getAdminContext();
        if (!$this->isTreatmentToggle($context)) {
            return;
        }

        /** @var Notification $notification */
        $notification = $context->getEntity()->getInstance();
        $context->getRequest()->attributes->set(self::PENDING_BEFORE_EDIT_ATTRIBUTE, $notification->getTreatment()?->isPending());
    }

    public function dispatchIfChanged(AfterCrudActionEvent $event): void
    {
        $context = $event->getAdminContext();
        if (!$this->isTreatmentToggle($context)) {
            return;
        }

        /** @var Notification $notification */
        $notification = $context->getEntity()->getInstance();
        if ($context->getRequest()->attributes->get(self::PENDING_BEFORE_EDIT_ATTRIBUTE) === $notification->getTreatment()?->isPending()) {
            return;
        }

        $this->eventDispatcher->dispatch(new NotificationTreatmentChangedEvent($notification));
    }

    /**
     * @param AdminContext<object>|null $context
     *
     * @phpstan-assert-if-true AdminContext<object> $context
     */
    private function isTreatmentToggle(?AdminContext $context): bool
    {
        $crud = $context?->getCrud();

        return $this->crudControllerFqcn === $crud?->getControllerFqcn()
            && Action::EDIT === $crud->getCurrentAction()
            && NotificationCrudController::ACTION_TREATMENT === $context->getRequest()->query->get('fieldName');
    }
}
