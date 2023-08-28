<?php

namespace SoureCode\Bundle\Worker\Manager;

use Doctrine\ORM\EntityManagerInterface;
use Exception;
use InvalidArgumentException;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use SoureCode\Bundle\Daemon\Manager\DaemonManager;
use SoureCode\Bundle\Worker\Entity\Worker;
use SoureCode\Bundle\Worker\Message\StartWorkerMessage;
use SoureCode\Bundle\Worker\Message\StopWorkerMessage;
use SoureCode\Bundle\Worker\Repository\WorkerRepository;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Contracts\Service\ServiceProviderInterface;

class WorkerManager
{
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
        ClockInterface           $clock
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
    }

    /**
     * @return true|int true if async or exit code
     * @throws Exception
     */
    public function startAsync(int $id): true|int
    {
        if ($this->workerRepository->hasRunningWorkers()) {
            $this->messageBus->dispatch(
                new StartWorkerMessage($id),
                [
                    new DispatchAfterCurrentBusStamp(),
                ]
            );

            return true;
        }

        return $this->start($id);
    }

    public function start(int $id): bool
    {
        $worker = $this->workerRepository->find($id);

        if (null === $worker) {
            throw new InvalidArgumentException(sprintf('Worker with id "%s" not found.', $id));
        }

        if ($worker->isRunning()) {
            $this->logger->warning(sprintf('Worker with id "%s" is already running.', $id));

            return 0;
        }

        $worker->setShouldExit(false);

        $this->entityManager->flush();

        $command = [
            ...$this->getPhpBinary(),
            $this->getConsolePath(),
            ...$worker->getCommand(),
        ];

        $commandLine = implode(" ", $command);

        $daemonId = self::getDaemonId($id);

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
        return 'soure_code_worker_' . $id;
    }

    /**
     * @return true|int true if async or exit code
     * @throws Exception
     */
    public function stopAsync(int $id): true|int
    {
        if ($this->workerRepository->hasRunningWorkers()) {
            $this->messageBus->dispatch(
                new StopWorkerMessage($id),
                [
                    new DispatchAfterCurrentBusStamp(),
                ]
            );

            return true;
        }

        return $this->stop($id);
    }

    public function stop(int $id): bool
    {
        if (!$this->stopGracefully($id)) {
            return false;
        }

        $daemonId = self::getDaemonId($id);

        return $this->daemonManager->stop($daemonId);
    }

    public function stopGracefully(int $id): bool
    {
        $worker = $this->workerRepository->find($id);

        if (null === $worker) {
            throw new InvalidArgumentException(sprintf('Worker with id "%s" not found.', $id));
        }

        if (!$worker->isRunning()) {
            $this->logger->warning(sprintf('Worker with id "%s" is not running.', $id));

            return false;
        }

        $worker->setShouldExit(true);
        $this->entityManager->flush();

        return true;
    }

    public function stopAll(): bool
    {
        $workers = $this->workerRepository->findAll();

        if (0 === count($workers)) {
            $this->logger->warning('No workers found.');

            return 0;
        }

        $stopped = [];

        foreach ($workers as $worker) {
            if ($worker->isRunning()) {
                $stopped[] = $this->stop($worker->getId());
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
                $stopped[] = $this->stopGracefully($worker->getId());
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
                $started[] = $this->start($worker->getId());
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
}