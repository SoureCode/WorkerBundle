<?php

namespace SoureCode\Bundle\Worker\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use SoureCode\Bundle\Worker\Entity\Worker;
use SoureCode\Bundle\Worker\Entity\WorkerStatus;

/**
 * @extends ServiceEntityRepository<Worker>
 *
 * @method Worker|null find($id, $lockMode = null, $lockVersion = null)
 * @method Worker|null findOneBy(array $criteria, array $orderBy = null)
 * @method Worker[]    findAll()
 * @method Worker[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class WorkerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Worker::class);
    }

    public function hasRunningWorkers(): bool
    {
        $queryBuilder = $this->createQueryBuilder('worker');

        $queryBuilder->select('COUNT(worker.id)')
            ->where($queryBuilder->expr()->neq('worker.status', ':status'))
            ->setParameter('status', WorkerStatus::OFFLINE);

        return (int)$queryBuilder->getQuery()->getSingleScalarResult() > 0;
    }
}
