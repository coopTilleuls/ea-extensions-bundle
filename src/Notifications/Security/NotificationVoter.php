<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\Security;

use CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\Entity\Notification;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @extends Voter<string, Notification>
 */
class NotificationVoter extends Voter
{
    public const string IS_RELATED = 'ea_extensions_notification_is_related';
    public const string IS_TREATABLE = 'ea_extensions_notification_is_treatable';

    public function __construct(
        private readonly NotificationRelationCheckerInterface $relationChecker,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return \in_array($attribute, [self::IS_RELATED, self::IS_TREATABLE], true) && $subject instanceof Notification;
    }

    /**
     * @param Notification $subject
     */
    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        return match ($attribute) {
            self::IS_RELATED => $this->relationChecker->isRelated($subject),
            self::IS_TREATABLE => null !== $subject->getTreatment(),
            default => false,
        };
    }
}
