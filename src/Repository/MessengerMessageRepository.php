<?php

namespace SoureCode\Bundle\Worker\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use SoureCode\Bundle\Worker\Entity\MessengerMessage;

/**
 * @extends ServiceEntityRepository<MessengerMessage>
 *
 * @method MessengerMessage|null find($id, $lockMode = null, $lockVersion = null)
 * @method MessengerMessage|null findOneBy(array $criteria, array $orderBy = null)
 * @method MessengerMessage[]    findAll()
 * @method MessengerMessage[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class MessengerMessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MessengerMessage::class);
    }
}
