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

    /**
     * @param iterable<NotificationRecipientInterface> $recipients
     */
    public function forRecipients(QueryBuilder $qb, iterable $recipients): QueryBuilder
    {
        $recipients = \is_array($recipients) ? $recipients : iterator_to_array($recipients, false);

        if ([] === $recipients) {
            // No resolvable recipient (e.g. the current Security user doesn't map to one) —
            // return no rows, rather than filtering on an empty IN().
            return $qb->andWhere('1 = 0');
        }

        $alias = $qb->getRootAliases()[0];

        return $qb->andWhere("$alias.recipient IN (:recipients)")
            ->setParameter('recipients', $recipients);
    }

    /**
     * @param iterable<NotificationRecipientInterface> $recipients
     */
    public function getUnreadCount(iterable $recipients): int
    {
        $qb = $this->forRecipients($this->createQueryBuilder('n'), $recipients);

        return (int) $qb->select('COUNT(n.id)')
            ->andWhere('n.read = FALSE')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @param iterable<NotificationRecipientInterface> $recipients
     *
     * @return list<Notification>
     */
    public function getLastUnread(iterable $recipients, int $limit = 3): array
    {
        $qb = $this->forRecipients($this->createQueryBuilder('n'), $recipients);

        /* @var list<Notification> */
        return $qb->andWhere('n.read = FALSE')
            ->orderBy('n.date', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
