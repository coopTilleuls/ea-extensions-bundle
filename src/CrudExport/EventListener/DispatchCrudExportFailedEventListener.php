<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\CrudExport\EventListener;

use CoopTilleuls\Bundle\EAExtensionsBundle\CrudExport\Event\CrudExportFailedEvent;
use CoopTilleuls\Bundle\EAExtensionsBundle\CrudExport\Message\ExportCrudMessage;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Dispatches CrudExportFailedEvent once Messenger gives up on an async export, so a failure
 * is reported once rather than on every retry.
 */
#[AsEventListener(event: WorkerMessageFailedEvent::class)]
final readonly class DispatchCrudExportFailedEventListener
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function __invoke(WorkerMessageFailedEvent $event): void
    {
        $message = $event->getEnvelope()->getMessage();

        if (!$message instanceof ExportCrudMessage || $event->willRetry()) {
            return;
        }

        // Messenger wraps the handler's exception, unwrap it so listeners get the actual cause.
        $exception = $event->getThrowable();
        if ($exception instanceof HandlerFailedException) {
            $exception = $exception->getWrappedExceptions()[0] ?? $exception;
        }

        $this->eventDispatcher->dispatch(new CrudExportFailedEvent(
            crudControllerFqcn: $message->crudControllerFqcn,
            exportName: $message->exportName,
            requesterId: $message->requesterId,
            exception: $exception,
            context: $message->context,
        ));
    }
}
