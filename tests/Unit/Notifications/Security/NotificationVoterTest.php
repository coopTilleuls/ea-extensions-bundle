<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Unit\Notifications\Security;

use CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\Entity\Notification;
use CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\Recipient\NotificationRecipientResolverInterface;
use CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\Security\NotificationVoter;
use CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Fixtures\StubNotificationRecipient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

final class NotificationVoterTest extends TestCase
{
    public function testGrantsAccessWhenTheSubjectsRecipientMatchesTheCurrentRecipient(): void
    {
        $notification = new Notification('information', 'Title')->setRecipient(new StubNotificationRecipient('recipient-1'));

        $vote = $this->vote($notification, new StubNotificationRecipient('recipient-1'));

        self::assertSame(VoterInterface::ACCESS_GRANTED, $vote);
    }

    public function testDeniesAccessWhenTheSubjectsRecipientDiffersFromTheCurrentRecipient(): void
    {
        $notification = new Notification('information', 'Title')->setRecipient(new StubNotificationRecipient('recipient-1'));

        $vote = $this->vote($notification, new StubNotificationRecipient('recipient-2'));

        self::assertSame(VoterInterface::ACCESS_DENIED, $vote);
    }

    public function testDeniesAccessWhenTheCurrentUserResolvesNoRecipient(): void
    {
        $notification = new Notification('information', 'Title')->setRecipient(new StubNotificationRecipient('recipient-1'));

        // e.g. an anonymous user, or a Security user that maps to no recipient — must never be
        // treated as "related" just because both sides happen to compare as unresolved.
        $vote = $this->vote($notification, null);

        self::assertSame(VoterInterface::ACCESS_DENIED, $vote);
    }

    private function vote(Notification $notification, ?StubNotificationRecipient $currentRecipient): int
    {
        $resolver = $this->createMock(NotificationRecipientResolverInterface::class);
        $resolver->method('resolveCurrentRecipient')->willReturn($currentRecipient);

        $voter = new NotificationVoter($resolver);

        return $voter->vote($this->createStub(TokenInterface::class), $notification, [NotificationVoter::IS_RELATED]);
    }
}
