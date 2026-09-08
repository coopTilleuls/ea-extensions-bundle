<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\EasyAdmin;

use CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\Entity\Notification;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Event\BeforeCrudActionEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * The "treatment.pending" toggle's stricter permission (IS_RELATED and IS_TREATABLE — see
 * NotificationCrudController::configureActions()) only gates whether the switch is *displayed*.
 * EasyAdmin's own edit()/ajaxEdit() only enforces the generic Action::EDIT permission (IS_RELATED)
 * for the actual PATCH submission, regardless of fieldName — so a related recipient could still
 * hit the toggle URL for a notification with no treatment attached at all. Denies that case
 * explicitly instead of relying on the PropertyAccessor crash that would otherwise follow from
 * writing "treatment.pending" onto a null treatment.
 */
#[AsEventListener(event: BeforeCrudActionEvent::class)]
final class NotificationTreatmentEditGuard
{
    public function __invoke(BeforeCrudActionEvent $event): void
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
        if (null === $notification->getTreatment()) {
            throw new AccessDeniedException('This notification has no treatment to update.');
        }
    }
}
