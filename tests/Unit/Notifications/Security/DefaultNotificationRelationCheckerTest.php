<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Unit\Notifications\Security;

use CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\Entity\Notification;
use CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\Recipient\NotificationRecipientResolverInterface;
use CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\Security\DefaultNotificationRelationChecker;
use CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Fixtures\StubNotificationRecipient;
use PHPUnit\Framework\TestCase;

final class DefaultNotificationRelationCheckerTest extends TestCase
{
    public function testGrantsAccessWhenTheSubjectsRecipientMatchesTheCurrentRecipient(): void
    {
        $notification = new Notification('information', 'Title')->setRecipient(new StubNotificationRecipient('recipient-1'));

        self::assertTrue($this->isRelated($notification, [new StubNotificationRecipient('recipient-1')]));
    }

    public function testGrantsAccessWhenTheSubjectsRecipientMatchesAnyOfSeveralCurrentRecipients(): void
    {
        $notification = new Notification('information', 'Title')->setRecipient(new StubNotificationRecipient('recipient-2'));

        self::assertTrue($this->isRelated($notification, [new StubNotificationRecipient('recipient-1'), new StubNotificationRecipient('recipient-2')]));
    }

    public function testDeniesAccessWhenTheSubjectsRecipientMatchesNoCurrentRecipient(): void
    {
        $notification = new Notification('information', 'Title')->setRecipient(new StubNotificationRecipient('recipient-1'));

        self::assertFalse($this->isRelated($notification, [new StubNotificationRecipient('recipient-2')]));
    }

    public function testDeniesAccessWhenTheCurrentUserResolvesNoRecipient(): void
    {
        $notification = new Notification('information', 'Title')->setRecipient(new StubNotificationRecipient('recipient-1'));

        // e.g. an anonymous user, or a Security user that maps to no recipient — must never be
        // treated as "related" just because there's simply nothing to compare against.
        self::assertFalse($this->isRelated($notification, []));
    }

    /**
     * @param list<StubNotificationRecipient> $currentRecipients
     */
    private function isRelated(Notification $notification, array $currentRecipients): bool
    {
        $resolver = $this->createMock(NotificationRecipientResolverInterface::class);
        $resolver->method('resolveCurrentRecipients')->willReturn($currentRecipients);

        return new DefaultNotificationRelationChecker($resolver)->isRelated($notification);
    }
}
