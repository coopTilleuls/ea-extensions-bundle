<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Unit\Notifications\Security;

use CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\Entity\Notification;
use CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\Security\NotificationRelationCheckerInterface;
use CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\Security\NotificationVoter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

final class NotificationVoterTest extends TestCase
{
    public function testGrantsAccessWhenTheRelationCheckerReturnsTrue(): void
    {
        $vote = $this->vote(true);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $vote);
    }

    public function testDeniesAccessWhenTheRelationCheckerReturnsFalse(): void
    {
        $vote = $this->vote(false);

        self::assertSame(VoterInterface::ACCESS_DENIED, $vote);
    }

    private function vote(bool $isRelated): int
    {
        $notification = new Notification('information', 'Title');

        $relationChecker = $this->createMock(NotificationRelationCheckerInterface::class);
        $relationChecker->method('isRelated')->with($notification)->willReturn($isRelated);

        $voter = new NotificationVoter($relationChecker);

        return $voter->vote($this->createStub(TokenInterface::class), $notification, [NotificationVoter::IS_RELATED]);
    }
}
