<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\TabNavigation\EasyAdmin;

use EasyCorp\Bundle\EasyAdminBundle\Event\BeforeCrudActionEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Appends the tab-navigation form theme to every CRUD action's form themes, so no consumer
 * CrudController (nor its Dashboard) has to declare it. BeforeCrudActionEvent fires once the
 * AdminContext — and thus the app's own configureCrud() form themes — is fully built, but before
 * the new/edit form is rendered: appended at that point, this theme ends up last, i.e. with the
 * highest priority (see CrudDto::addFormTheme()), so its two tab-pane blocks win over vendor's and
 * over any theme extending vendor's.
 */
#[AsEventListener(event: BeforeCrudActionEvent::class)]
final class TabNavigationFormThemeListener
{
    public const FORM_THEME = '@EAExtensions/tab_navigation/form_theme.html.twig';

    public function __invoke(BeforeCrudActionEvent $event): void
    {
        $crud = $event->getAdminContext()?->getCrud();
        if (null === $crud || \in_array(self::FORM_THEME, $crud->getFormThemes(), true)) {
            return;
        }

        $crud->addFormTheme(self::FORM_THEME);
    }
}
