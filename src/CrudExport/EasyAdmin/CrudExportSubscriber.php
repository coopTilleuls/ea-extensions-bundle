<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\CrudExport\EasyAdmin;

use CoopTilleuls\Bundle\EAExtensionsBundle\CrudExport\ColumnResolver;
use CoopTilleuls\Bundle\EAExtensionsBundle\CrudExport\CrudExportAction;
use CoopTilleuls\Bundle\EAExtensionsBundle\CrudExport\CrudExportColumn;
use CoopTilleuls\Bundle\EAExtensionsBundle\CrudExport\CrudExportConfigurationInterface;
use CoopTilleuls\Bundle\EAExtensionsBundle\CrudExport\CrudExportFormat;
use CoopTilleuls\Bundle\EAExtensionsBundle\CrudExport\CrudExportMode;
use CoopTilleuls\Bundle\EAExtensionsBundle\CrudExport\CrudExportSettings;
use CoopTilleuls\Bundle\EAExtensionsBundle\CrudExport\Event\CrudExportFileReadyEvent;
use CoopTilleuls\Bundle\EAExtensionsBundle\CrudExport\File\CrudExportFileDownloader;
use CoopTilleuls\Bundle\EAExtensionsBundle\CrudExport\File\CrudExportGenerator;
use CoopTilleuls\Bundle\EAExtensionsBundle\CrudExport\Message\ExportCrudMessage;
use CoopTilleuls\Bundle\EAExtensionsBundle\CrudExport\Normalizer\CrudExportParameterNormalizer;
use CoopTilleuls\Bundle\EAExtensionsBundle\CrudExport\QueryBuilder\CrudExportQueryBuilder;
use CoopTilleuls\Bundle\EAExtensionsBundle\CrudExport\Requester\CrudExportRequesterResolverInterface;
use Doctrine\ORM\Query;
use EasyCorp\Bundle\EasyAdminBundle\Collection\EntityCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Controller\CrudControllerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Event\AfterCrudActionEvent;
use EasyCorp\Bundle\EasyAdminBundle\Factory\ControllerFactory;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Intercepts the index page after EasyAdmin has already built it (permission check,
 * SearchDto, FilterCollection — all reused for free), detects the export flag, and
 * short-circuits the response. See CrudExportActionsExtension for how the flag is set.
 */
#[AsEventListener(event: AfterCrudActionEvent::class)]
final readonly class CrudExportSubscriber
{
    public function __construct(
        private ControllerFactory $controllerFactory,
        private CrudExportQueryBuilder $queryBuilder,
        private ColumnResolver $columnResolver,
        private CrudExportGenerator $generator,
        private CrudExportFileDownloader $downloader,
        private CrudExportRequesterResolverInterface $requesterResolver,
        private CrudExportSettings $settings,
        private MessageBusInterface $messageBus,
        private CrudExportParameterNormalizer $normalizer,
        private EventDispatcherInterface $eventDispatcher,
        private AdminUrlGeneratorInterface $adminUrlGenerator,
        private TranslatorInterface $translator,
        private Security $security,
    ) {
    }

    public function __invoke(AfterCrudActionEvent $event): void
    {
        $context = $event->getAdminContext();
        if (null === $context || Crud::PAGE_INDEX !== $context->getCrud()?->getCurrentPage()) {
            return;
        }

        $request = $context->getRequest();
        if (!$request->query->getBoolean(CrudExportAction::QUERY_PARAM_TRIGGER)) {
            return;
        }

        $crudControllerFqcn = $context->getCrud()->getControllerFqcn();
        if (null === $crudControllerFqcn) {
            return;
        }

        /** @var class-string<CrudExportConfigurationInterface>|null $configured */
        $configured = is_subclass_of($crudControllerFqcn, CrudExportConfigurationInterface::class)
            ? $crudControllerFqcn
            : null;

        // Not routed through CrudExportActionsExtension (no button) on its own — this is
        // the actual enforcement, since the export flag could still be crafted by hand.
        if (null !== $configured && !$configured::isExportEnabled()) {
            return;
        }

        $permission = null !== $configured ? $configured::getExportPermission() : null;
        if (null !== $permission && !$this->security->isGranted($permission)) {
            throw new AccessDeniedException('You are not allowed to export this list.');
        }

        $controller = $this->controllerFactory->getCrudControllerInstance($crudControllerFqcn, Action::INDEX, $request);
        if (null === $controller) {
            return;
        }

        [$query, $count] = $this->queryBuilder->build($context, $controller);

        $format = (null !== $configured ? $configured::getExportFormat() : null) ?? CrudExportFormat::CSV;
        $requesterId = $this->requesterResolver->resolve($context);
        $exportName = (null !== $configured ? $configured::getExportName($context) : null) ?? $this->defaultExportName($context);

        if ($count > $this->settings->maxLimit) {
            $event->setResponse($this->redirectWithFlash(
                $request,
                'danger',
                sprintf('Le nombre de résultats à exporter (%d) dépasse la limite autorisée (%d). Veuillez affiner votre sélection.', $count, $this->settings->maxLimit),
            ));

            return;
        }

        $columns = $this->columnResolver->resolve($this->resolveFields($event, $controller));

        $mode = null !== $configured ? $configured::getExportMode() : null;
        $shouldRunAsync = match ($mode) {
            CrudExportMode::Sync => false,
            CrudExportMode::Async => true,
            null => $count >= $this->settings->asyncThreshold,
        };

        if ($shouldRunAsync) {
            $this->dispatchAsync($query, $columns, $crudControllerFqcn, $requesterId, $exportName, $format);

            $event->setResponse($this->redirectWithFlash(
                $request,
                'warning',
                "Le fichier d'export est en cours de préparation. Vous recevrez une notification lorsqu'il sera disponible.",
            ));

            return;
        }

        $written = $this->generator->generate($query, $columns, $crudControllerFqcn, $requesterId, $exportName, $format);

        $this->eventDispatcher->dispatch(new CrudExportFileReadyEvent(
            crudControllerFqcn: $crudControllerFqcn,
            exportName: $exportName,
            requesterId: $requesterId,
            storagePath: $written['storagePath'],
            filename: $written['filename'],
            async: false,
            downloadUrl: null,
        ));

        $event->setResponse($this->downloader->download($written['storagePath'], $written['filename']));
    }

    /**
     * @param Query<null, mixed>     $query
     * @param class-string           $crudControllerFqcn
     * @param list<CrudExportColumn> $columns
     */
    private function dispatchAsync(Query $query, array $columns, string $crudControllerFqcn, string $requesterId, string $exportName, string $format): void
    {
        $dql = $query->getDQL() ?? throw new \RuntimeException('Unable to retrieve the DQL for the export query.');

        $parameters = [];
        foreach ($query->getParameters() as $parameter) {
            $parameters[$parameter->getName()] = $this->normalizer->normalizeParameterValue($parameter->getValue());
        }

        $this->messageBus->dispatch(new ExportCrudMessage($crudControllerFqcn, $dql, $parameters, $exportName, $requesterId, $columns, $format));
    }

    /**
     * @param CrudControllerInterface<object> $controller
     */
    private function resolveFields(AfterCrudActionEvent $event, CrudControllerInterface $controller): FieldCollection
    {
        $entities = $event->getResponseParameters()->get('entities');
        if ($entities instanceof EntityCollection) {
            $fields = $entities->first()?->getFields();
            if (null !== $fields && !$fields->isEmpty()) {
                return $fields;
            }
        }

        // No rows on the current page (empty filter result) — the entity-derived
        // FieldCollection above is unavailable, so fall back to the controller's raw
        // field definitions. Loses any CustomizableColumns selection in that edge case,
        // but there is nothing to export anyway.
        return new FieldCollection($controller->configureFields(Crud::PAGE_INDEX));
    }

    /**
     * @param AdminContext<object> $context
     */
    private function defaultExportName(AdminContext $context): string
    {
        $label = $context->getCrud()?->getEntityLabelInPlural() ?? 'export';
        if ($label instanceof TranslatableInterface) {
            $label = $label->trans($this->translator);
        }

        return mb_strtolower($label);
    }

    private function redirectWithFlash(Request $request, string $type, string $message): RedirectResponse
    {
        if ($request->hasSession()) {
            $session = $request->getSession();
            if ($session instanceof FlashBagAwareSessionInterface) {
                $session->getFlashBag()->add($type, $message);
            }
        }

        return new RedirectResponse($this->adminUrlGenerator->unset(CrudExportAction::QUERY_PARAM_TRIGGER)->generateUrl());
    }
}
