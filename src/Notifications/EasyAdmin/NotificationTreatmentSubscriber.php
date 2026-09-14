<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\EasyAdmin;

use CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\Entity\Notification;
use CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\Event\NotificationTreatmentChangedEvent;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Event\AfterCrudActionEvent;
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
 */
#[AsEventListener(event: AfterCrudActionEvent::class)]
final class NotificationTreatmentSubscriber
{
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function __invoke(AfterCrudActionEvent $event): void
    {
        $context = $event->getAdminContext();
        $crud = $context?->getCrud();
        if (NotificationCrudController::class !== $crud?->getControllerFqcn() || Action::EDIT !== $crud->getCurrentAction()) {
            return;
        }

        if (NotificationCrudController::ACTION_TREATMENT !== $context->getRequest()->query->get('fieldName')) {
            return;
        }

        /** @var Notification $notification */
        $notification = $context->getEntity()->getInstance();

        $this->eventDispatcher->dispatch(new NotificationTreatmentChangedEvent($notification));
    }
}
