<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\Repository;

use CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\Entity\Notification;
use CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\Recipient\NotificationRecipientInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Notification>
 */
class NotificationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Notification::class);
    }

    public function forRecipient(QueryBuilder $qb, ?NotificationRecipientInterface $recipient): QueryBuilder
    {
        if (null === $recipient) {
            // No resolvable recipient (e.g. the current Security user doesn't map to one) —
            // return no rows, rather than filtering on a null parameter.
            return $qb->andWhere('1 = 0');
        }

        $alias = $qb->getRootAliases()[0];

        return $qb->andWhere("$alias.recipient = :recipient")
            ->setParameter('recipient', $recipient);
    }

    public function getUnreadCount(?NotificationRecipientInterface $recipient): int
    {
        $qb = $this->forRecipient($this->createQueryBuilder('n'), $recipient);

        return (int) $qb->select('COUNT(n.id)')
            ->andWhere('n.unread = TRUE')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return list<Notification>
     */
    public function getLastUnread(?NotificationRecipientInterface $recipient, int $limit = 3): array
    {
        $qb = $this->forRecipient($this->createQueryBuilder('n'), $recipient);

        /* @var list<Notification> */
        return $qb->andWhere('n.unread = TRUE')
            ->orderBy('n.date', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
