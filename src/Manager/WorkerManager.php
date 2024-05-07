<?php

namespace SoureCode\Bundle\Worker\Manager;

use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Psr\Clock\ClockInterface;
use SoureCode\Bundle\Daemon\Manager\DaemonManager;
use SoureCode\Bundle\Worker\Daemon\DumperInterface;
use SoureCode\Bundle\Worker\Entity\Worker;
use SoureCode\Bundle\Worker\Entity\WorkerStatus;
use SoureCode\Bundle\Worker\Repository\WorkerRepository;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Contracts\Service\ServiceProviderInterface;

class WorkerManager
{
    public static string $daemonPrefix = 'soure_code_worker_';

    public function __construct(
        private readonly DaemonManager            $daemonManager,
        private readonly EntityManagerInterface   $entityManager,
        private readonly WorkerRepository         $workerRepository,
        private readonly ?string                  $globalFailureReceiverName,
        private readonly ServiceProviderInterface $failureTransports,
        private readonly array                    $receiverNames,
        private readonly array                    $busIds,
        private readonly ClockInterface           $clock,
        private readonly SerializerInterface      $serializer,
        private readonly DumperInterface          $dumper,
    )
    {
    }

    public function stopAll(): bool
    {
        $workers = $this->workerRepository->findAll();

        if (0 === count($workers)) {
            return true;
        }

        $stopped = [];

        foreach ($workers as $worker) {
            $stopped[] = $this->stop($worker);
        }

        return !in_array(false, $stopped, true);
    }

    public function stop(Worker|int $workerOrId): bool
    {
        $worker = $workerOrId;

        if (is_int($worker)) {
            $worker = $this->workerRepository->find($workerOrId);

            if (null === $worker) {
                throw new InvalidArgumentException(sprintf('Worker with id "%s" not found.', $workerOrId));
            }
        }

        if (!$this->isRunning($worker)) {
            return true;
        }

        $daemonId = self::getDaemonId($worker->getId());

        $stopped = $this->daemonManager->stop($daemonId);

        $this->dumper->remove($worker);
        $this->daemonManager->reset();

        return $stopped;
    }

    public function isRunning(Worker|int $workerOrId): bool
    {
        $worker = $workerOrId;

        if (is_int($workerOrId)) {
            $worker = $this->workerRepository->find($workerOrId);

            if (null === $worker) {
                throw new InvalidArgumentException(sprintf('Worker with id "%s" not found.', $workerOrId));
            }
        }

        $daemonId = self::getDaemonId($worker->getId());

        return $this->daemonManager->isRunning($daemonId);
    }

    public static function getDaemonId(int $id): string
    {
        return self::$daemonPrefix . $id;
    }

    public function startAll(): bool
    {
        $workers = $this->workerRepository->findAll();

        if (0 === count($workers)) {
            return true;
        }

        $started = [];

        foreach ($workers as $worker) {
            $started[] = $this->start($worker);
        }

        return !in_array(false, $started, true);
    }

    public function restartAll(): bool
    {
        $workers = $this->workerRepository->findAll();

        if (0 === count($workers)) {
            return true;
        }

        $restarted = [];

        foreach ($workers as $worker) {
            $restarted[] = $this->restart($worker);
        }

        return !in_array(false, $restarted, true);
    }


    public function start(Worker|int $workerOrId): bool
    {
        $worker = $workerOrId;

        if (is_int($workerOrId)) {
            $worker = $this->workerRepository->find($workerOrId);

            if (null === $worker) {
                throw new InvalidArgumentException(sprintf('Worker with id "%s" not found.', $workerOrId));
            }
        }

        if ($this->isRunning($worker)) {
            return true;
        }

        $this->dumper->dump($worker);
        $this->daemonManager->reset();

        $daemonId = self::getDaemonId($worker->getId());

        return $this->daemonManager->start($daemonId);
    }

    public function getReceiverNames(): array
    {
        return $this->receiverNames;
    }

    public function getBusIds(): array
    {
        return $this->busIds;
    }

    public function getFailureTransports(): ServiceProviderInterface
    {
        return $this->failureTransports;
    }

    public function getGlobalFailureReceiverName(): ?string
    {
        return $this->globalFailureReceiverName;
    }

    public function getFailureTransportNames(): array
    {
        return array_keys($this->failureTransports->getProvidedServices());
    }

    public function evaluateWorkers(): void
    {
        $workers = $this->workerRepository->findAll();

        foreach ($workers as $worker) {
            $this->evaluateWorkerStatus($worker);
        }

        $this->entityManager->flush();
    }

    public function evaluateWorkerStatus(Worker $worker): void
    {
        $daemonId = self::getDaemonId($worker->getId());

        if (!$this->isRunning($worker)) {
            $worker->setStatus(WorkerStatus::OFFLINE);
            $worker->setStartedAt(null);
            $worker->setLastHeartbeat(null);
        } else {
            $worker->setLastHeartbeat($this->clock->now());
        }
    }

    public function decodeMessage(array $data): Envelope
    {
        return $this->serializer->decode($data);
    }

    public function encodeMessage(Envelope $data): array
    {
        return $this->serializer->encode($data);
    }

    public function reload(Worker|int $workerOrId): bool
    {
        $worker = $workerOrId;

        if (is_int($workerOrId)) {
            $worker = $this->workerRepository->find($workerOrId);

            if (null === $worker) {
                throw new InvalidArgumentException(sprintf('Worker with id "%s" not found.', $workerOrId));
            }
        }

        $this->dumper->dump($worker);
        $this->daemonManager->reset();

        $daemonId = self::getDaemonId($worker->getId());

        return $this->daemonManager->reload($daemonId);
    }

    public function restart(Worker|int $workerOrId): bool
    {
        $worker = $workerOrId;

        if (is_int($workerOrId)) {
            $worker = $this->workerRepository->find($workerOrId);

            if (null === $worker) {
                throw new InvalidArgumentException(sprintf('Worker with id "%s" not found.', $workerOrId));
            }
        }

        $daemonId = self::getDaemonId($worker->getId());

        return $this->daemonManager->restart($daemonId);
    }

}
