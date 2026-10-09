<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Functional;

use CoopTilleuls\Bundle\EAExtensionsBundle\BackToTop\EasyAdmin\BackToTopListener;
use CoopTilleuls\Bundle\EAExtensionsBundle\TabNavigation\EasyAdmin\TabNavigationFormThemeListener;
use CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Fixtures\DisabledNotificationsKernel;
use CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Fixtures\Kernel;
use EasyCorp\Bundle\EasyAdminBundle\Event\BeforeCrudActionEvent;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Both features are opt-in: {@see DisabledNotificationsKernel} leaves them out of its config, so
 * neither listener may end up registered there — otherwise every app would get the buttons
 * injected into its pages just by installing the bundle. The enabled-kernel counterpart keeps the
 * disabled assertions from passing vacuously.
 */
final class TabNavigationAndBackToTopRegistrationTest extends TestCase
{
    public function testListenersAreRegisteredWhenEnabled(): void
    {
        $kernel = new Kernel('test', false);

        self::assertContains(TabNavigationFormThemeListener::class, self::listenerClasses($kernel, BeforeCrudActionEvent::class));
        self::assertContains(BackToTopListener::class, self::listenerClasses($kernel, KernelEvents::CONTROLLER));
    }

    public function testListenersAreNotRegisteredByDefault(): void
    {
        $kernel = new DisabledNotificationsKernel('test', false);

        self::assertNotContains(TabNavigationFormThemeListener::class, self::listenerClasses($kernel, BeforeCrudActionEvent::class));
        self::assertNotContains(BackToTopListener::class, self::listenerClasses($kernel, KernelEvents::CONTROLLER));
    }

    /**
     * @return list<string>
     */
    private static function listenerClasses(KernelInterface $kernel, string $eventName): array
    {
        $kernel->boot();

        try {
            /** @var EventDispatcherInterface $dispatcher */
            $dispatcher = $kernel->getContainer()->get('event_dispatcher');

            // Lazy listeners are resolved by getListeners() into [$instance, $method] pairs.
            return array_values(array_map(
                static fn (callable|array $listener): string => \is_array($listener) && \is_object($listener[0]) ? $listener[0]::class : get_debug_type($listener),
                $dispatcher->getListeners($eventName),
            ));
        } finally {
            $kernel->shutdown();
        }
    }
}
