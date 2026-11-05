<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\EasyAdmin;

use CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\Entity\Notification;
use CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\Event\NotificationMarkedAsReadEvent;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Event\AfterCrudActionEvent;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[AsEventListener(event: AfterCrudActionEvent::class)]
final class NotificationDetailReadSubscriber
{
    public function __construct(
        #[Autowire(param: 'coop_tilleuls_ea_extensions.notifications.crud_controller')]
        private readonly string $crudControllerFqcn,
        private readonly EntityManagerInterface $entityManager,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function __invoke(AfterCrudActionEvent $event): void
    {
        $crud = $event->getAdminContext()?->getCrud();
        if ($this->crudControllerFqcn !== $crud?->getControllerFqcn() || Crud::PAGE_DETAIL !== $crud->getCurrentPage()) {
            return;
        }

        /** @var Notification $notification */
        $notification = $event->getAdminContext()->getEntity()->getInstance();
        if ($notification->isRead()) {
            return;
        }

        $notification->setRead(true);
        $this->entityManager->flush();

        $this->eventDispatcher->dispatch(new NotificationMarkedAsReadEvent($notification));
    }
}
