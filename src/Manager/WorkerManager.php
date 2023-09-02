<?php

namespace SoureCode\Bundle\Worker\Manager;

use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use SoureCode\Bundle\Daemon\Manager\DaemonManager;
use SoureCode\Bundle\Worker\Entity\Worker;
use SoureCode\Bundle\Worker\Message\StartWorkerMessage;
use SoureCode\Bundle\Worker\Message\StopWorkerMessage;
use SoureCode\Bundle\Worker\Repository\WorkerRepository;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Contracts\Service\ServiceProviderInterface;

#[Autoconfigure(tags: ['monolog.logger' => ['channel' => 'worker']])]
class WorkerManager
{
    public static string $daemonPrefix = 'soure_code_worker_';
    private LoggerInterface $logger;
    private WorkerRepository $workerRepository;
    private string $projectDirectory;
    private EntityManagerInterface $entityManager;
    private ServiceProviderInterface $failureTransports;
    private array $receiverNames;
    private array $busIds;
    private ?string $globalFailureReceiverName;
    private MessageBusInterface $messageBus;
    private DaemonManager $daemonManager;
    private ClockInterface $clock;
    private SerializerInterface $serializer;

    public function __construct(
        DaemonManager            $daemonManager,
        EntityManagerInterface   $entityManager,
        LoggerInterface          $logger,
        WorkerRepository         $workerRepository,
        string                   $projectDirectory,
        ?string                  $globalFailureReceiverName,
        ServiceProviderInterface $failureTransports,
        array                    $receiverNames,
        array                    $busIds,
        MessageBusInterface      $messageBus,
        ClockInterface           $clock,
        SerializerInterface      $serializer,
    )
    {
        $this->entityManager = $entityManager;
        $this->logger = $logger;
        $this->workerRepository = $workerRepository;
        $this->projectDirectory = $projectDirectory;
        $this->failureTransports = $failureTransports;
        $this->receiverNames = $receiverNames;
        $this->busIds = $busIds;
        $this->globalFailureReceiverName = $globalFailureReceiverName;
        $this->messageBus = $messageBus;
        $this->daemonManager = $daemonManager;
        $this->clock = $clock;
        $this->serializer = $serializer;
    }

    /**
     * @return bool true if start message was dispatched or worker was started, depends on the current state
     */
    public function startAsync(Worker|int $workerOrId): bool
    {
        if ($this->workerRepository->hasRunningWorkers()) {
            $id = $workerOrId;

            if ($workerOrId instanceof Worker) {
                $id = $workerOrId->getId();
            }

            $this->messageBus->dispatch(
                new StartWorkerMessage($id),
                [
                    new DispatchAfterCurrentBusStamp(),
                ]
            );

            return true;
        }

        return $this->start($workerOrId);
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

        if ($worker->isRunning()) {
            $this->logger->warning(sprintf('Worker with id "%s" is already running.', $worker->getId()));

            return 0;
        }

        $worker->offline();

        $this->entityManager->flush();

        $command = [
            ...$this->getPhpBinary(),
            $this->getConsolePath(),
            ...$worker->getCommand(),
        ];

        $commandLine = implode(" ", $command);

        $daemonId = self::getDaemonId($worker->getId());

        return $this->daemonManager->start($daemonId, $commandLine);
    }

    private function getPhpBinary(): ?array
    {
        $executableFinder = new PhpExecutableFinder();
        $php = $executableFinder->find(false);

        if (false === $php) {
            return null;
        }

        return array_merge([$php], $executableFinder->findArguments());
    }

    private function getConsolePath(): string
    {
        return Path::join($this->projectDirectory, 'bin', 'console');
    }

    public static function getDaemonId(int $id): string
    {
        return self::$daemonPrefix . $id;
    }

    /**
     * @return bool true if stop message was dispatched or worker was stopped, depends on the current state
     */
    public function stopAsync(Worker|int $workerOrId, ?int $timeout = null, ?array $signals = null): bool
    {
        if ($this->workerRepository->hasRunningWorkers()) {
            $id = $workerOrId;

            if ($workerOrId instanceof Worker) {
                $id = $workerOrId->getId();
            }

            $this->messageBus->dispatch(
                new StopWorkerMessage($id, $timeout, $signals),
                [
                    new DispatchAfterCurrentBusStamp(),
                ]
            );

            return true;
        }

        return $this->stop($workerOrId, $timeout, $signals);
    }

    public function stop(Worker|int $workerOrId, ?int $timeout = null, ?array $signals = null): bool
    {
        if (!$this->stopGracefully($workerOrId)) {
            return false;
        }

        $id = $workerOrId;

        if ($workerOrId instanceof Worker) {
            $id = $workerOrId->getId();
        }

        $daemonId = self::getDaemonId($id);

        return $this->daemonManager->stop($daemonId, $timeout, $signals);
    }

    public function stopGracefully(Worker|int $workerOrId): bool
    {
        $worker = $workerOrId;

        if (is_int($worker)) {
            $worker = $this->workerRepository->find($workerOrId);

            if (null === $worker) {
                throw new InvalidArgumentException(sprintf('Worker with id "%s" not found.', $workerOrId));
            }
        }

        if (!$worker->isRunning()) {
            $this->logger->warning(sprintf('Worker with id "%s" is not running.', $worker->getId()));

            return false;
        }

        $worker->setShouldExit(true);
        $this->entityManager->flush();

        return true;
    }

    /**
     * @param bool $byPidFiles If true, the pid files will be used to stop the workers, not the database.
     * @return bool true if all workers were stopped successfully
     */
    public function stopAll(bool $byPidFiles = false, ?int $timeout = null, ?array $signals = null): bool
    {
        if ($byPidFiles) {
            return $this->daemonManager->stopAll('/^soure_code_worker_\d+$/', $timeout, $signals);
        }

        $workers = $this->workerRepository->findAll();

        if (0 === count($workers)) {
            $this->logger->warning('No workers found.');

            return 0;
        }

        $stopped = [];

        foreach ($workers as $worker) {
            if ($worker->isRunning()) {
                $stopped[] = $this->stop($worker, $timeout, $signals);
            }
        }

        return !in_array(false, $stopped, true);
    }

    public function stopAllGracefully(): bool
    {
        $workers = $this->workerRepository->findAll();

        if (0 === count($workers)) {
            $this->logger->warning('No workers found.');

            return false;
        }

        $stopped = [];

        foreach ($workers as $worker) {
            if ($worker->isRunning()) {
                $stopped[] = $this->stopGracefully($worker);
            }
        }

        return !in_array(false, $stopped, true);
    }

    public function startAll(): bool
    {
        $workers = $this->workerRepository->findAll();

        if (0 === count($workers)) {
            $this->logger->warning('No workers found.');

            return 0;
        }

        $started = [];

        foreach ($workers as $worker) {
            if (!$worker->isRunning()) {
                $started[] = $this->start($worker);
            }
        }

        return !in_array(false, $started, true);
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
        $isRunning = $this->daemonManager->isRunning($daemonId);

        if (!$isRunning) {
            $worker->offline();
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
}