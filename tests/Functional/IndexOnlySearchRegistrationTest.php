<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Functional;

use CoopTilleuls\Bundle\EAExtensionsBundle\IndexOnlySearch\EasyAdmin\IndexOnlySearchListener;
use CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Fixtures\DisabledNotificationsKernel;
use CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Fixtures\Kernel;
use EasyCorp\Bundle\EasyAdminBundle\Event\BeforeCrudActionEvent;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * The feature is opt-in: {@see DisabledNotificationsKernel} leaves it out of its config, so the
 * listener may not end up registered there — otherwise every app would lose its top-bar search
 * outside index pages just by installing the bundle. The enabled-kernel counterpart keeps the
 * disabled assertions from passing vacuously.
 */
final class IndexOnlySearchRegistrationTest extends TestCase
{
    public function testListenerIsRegisteredWhenEnabled(): void
    {
        $kernel = new Kernel('test', false);

        self::assertContains(IndexOnlySearchListener::class, self::listenerClasses($kernel, BeforeCrudActionEvent::class));
        self::assertContains(IndexOnlySearchListener::class, self::listenerClasses($kernel, KernelEvents::VIEW));
    }

    public function testListenerIsNotRegisteredByDefault(): void
    {
        $kernel = new DisabledNotificationsKernel('test', false);

        self::assertNotContains(IndexOnlySearchListener::class, self::listenerClasses($kernel, BeforeCrudActionEvent::class));
        self::assertNotContains(IndexOnlySearchListener::class, self::listenerClasses($kernel, KernelEvents::VIEW));
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
