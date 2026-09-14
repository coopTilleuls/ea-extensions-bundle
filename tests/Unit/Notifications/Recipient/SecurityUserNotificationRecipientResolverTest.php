<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Unit\Notifications\Recipient;

use CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\Recipient\NotificationRecipientInterface;
use CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\Recipient\SecurityUserNotificationRecipientResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\UserInterface;

final class SecurityUserNotificationRecipientResolverTest extends TestCase
{
    public function testResolvesTheCurrentUserWhenItImplementsTheInterface(): void
    {
        $user = new class implements UserInterface, NotificationRecipientInterface {
            public function getId(): string
            {
                return 'admin';
            }

            public function getUserIdentifier(): string
            {
                return 'admin';
            }

            public function getRoles(): array
            {
                return [];
            }

            public function eraseCredentials(): void
            {
            }
        };

        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($user);

        $resolver = new SecurityUserNotificationRecipientResolver($security);

        self::assertSame($user, $resolver->resolveCurrentRecipient());
        self::assertSame([$user], [...$resolver->resolveCurrentRecipients()]);
    }

    public function testReturnsNullWhenNoUserIsLoggedIn(): void
    {
        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn(null);

        $resolver = new SecurityUserNotificationRecipientResolver($security);

        self::assertNull($resolver->resolveCurrentRecipient());
        self::assertSame([], [...$resolver->resolveCurrentRecipients()]);
    }

    public function testReturnsNullWhenTheUserDoesNotImplementTheInterface(): void
    {
        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn(new InMemoryUser('admin', 'password'));

        $resolver = new SecurityUserNotificationRecipientResolver($security);

        self::assertNull($resolver->resolveCurrentRecipient());
        self::assertSame([], [...$resolver->resolveCurrentRecipients()]);
    }
}
