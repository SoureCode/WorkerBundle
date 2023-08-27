<?php

namespace SoureCode\Bundle\Worker\Manager;

use Doctrine\ORM\EntityManagerInterface;
use Exception;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use SoureCode\Bundle\Daemon\Command\DaemonStartCommand;
use SoureCode\Bundle\Daemon\Command\DaemonStopCommand;
use SoureCode\Bundle\Worker\Repository\WorkerRepository;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Contracts\Service\ServiceProviderInterface;

class WorkerManager
{
    private KernelInterface $kernel;
    private LoggerInterface $logger;
    private WorkerRepository $workerRepository;
    private string $projectDirectory;
    private EntityManagerInterface $entityManager;
    private ServiceProviderInterface $failureTransports;
    private array $receiverNames;
    private array $busIds;
    private ?string $globalFailureReceiverName;

    public function __construct(
        KernelInterface          $kernel,
        EntityManagerInterface   $entityManager,
        LoggerInterface          $logger,
        WorkerRepository         $workerRepository,
        string                   $projectDirectory,
        ?string                  $globalFailureReceiverName,
        ServiceProviderInterface $failureTransports,
        array                    $receiverNames,
        array                    $busIds,
    )
    {
        $this->kernel = $kernel;
        $this->entityManager = $entityManager;
        $this->logger = $logger;
        $this->workerRepository = $workerRepository;
        $this->projectDirectory = $projectDirectory;
        $this->failureTransports = $failureTransports;
        $this->receiverNames = $receiverNames;
        $this->busIds = $busIds;
        $this->globalFailureReceiverName = $globalFailureReceiverName;
    }

    /**
     * @throws Exception
     */
    public function start(int $id): int
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

        return $this->run([
            'command' => DaemonStartCommand::getDefaultName(),
            '-vv' => true,
            '--id' => 'worker_' . $id,
            'process' => $commandLine,
        ]);
    }

    /**
     * @throws Exception
     */
    public function stop(int $id): int
    {
        $worker = $this->workerRepository->find($id);

        if (null === $worker) {
            throw new InvalidArgumentException(sprintf('Worker with id "%s" not found.', $id));
        }

        if (!$worker->isRunning()) {
            $this->logger->warning(sprintf('Worker with id "%s" is not running.', $id));

            return 0;
        }

        $worker->setShouldExit(true);
        $this->entityManager->flush();

        // Wait for next iteration and hope we can stop the worker gracefully.
        // Just +1 second to be sure.
        sleep(1 + $worker->getSleep());

        return $this->run([
            'command' => DaemonStopCommand::getDefaultName(),
            '-vv' => true,
            '--id' => 'worker_' . $id,
        ]);
    }

    public function stopAll(): int
    {
        $workers = $this->workerRepository->findAll();

        if (0 === count($workers)) {
            $this->logger->warning('No workers found.');

            return 0;
        }

        $exitCodes = [0, 0];

        foreach ($workers as $worker) {
            if ($worker->isRunning()) {
                $exitCodes[$worker->getId()] = $this->stop($worker->getId());
            }
        }

        return max(...$exitCodes);
    }

    /**
     * @throws Exception
     */
    private function run(array $inputParameters, ?OutputInterface $output = null): int
    {
        $application = new Application($this->kernel);
        $application->setAutoExit(false);

        $input = new ArrayInput($inputParameters);

        return $application->run($input, $output);
    }

    /**
     * @throws Exception
     */
    public function startAll(): int
    {
        $workers = $this->workerRepository->findAll();

        if (0 === count($workers)) {
            $this->logger->warning('No workers found.');

            return 0;
        }

        $exitCodes = [0, 0];

        foreach ($workers as $worker) {
            if (!$worker->isRunning()) {
                $exitCodes[$worker->getId()] = $this->start($worker->getId());
            }
        }

        return max(...$exitCodes);
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

    /**
     * @copyright Symfony Process Component.
     */
    private function escape(string $argument): string
    {
        if ('' === $argument || null === $argument) {
            return '""';
        }

        if ('\\' !== \DIRECTORY_SEPARATOR) {
            return "'" . str_replace("'", "'\\''", $argument) . "'";
        }

        if (str_contains($argument, "\0")) {
            $argument = str_replace("\0", '?', $argument);
        }

        if (!preg_match('/[\/()%!^"<>&|\s]/', $argument)) {
            return $argument;
        }

        $argument = preg_replace('/(\\\\+)$/', '$1$1', $argument);

        return '"' . str_replace(['"', '^', '%', '!', "\n"], ['""', '"^^"', '"^%"', '"^!"', '!LF!'], $argument) . '"';
    }

    private function getConsolePath(): string
    {
        return Path::join($this->projectDirectory, 'bin', 'console');
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

    /**
     * @copyright Symfony Messenger Component
     */
    protected function getFailureReceiver(string $name = null): ReceiverInterface
    {
        if (null === $name ??= $this->globalFailureReceiverName) {
            throw new InvalidArgumentException(
                sprintf('No default failure transport is defined. Available transports are: "%s".',
                    implode('", "', $this->getFailureTransportNames())
                ));
        }

        if (!$this->failureTransports->has($name)) {
            throw new InvalidArgumentException(
                sprintf('The "%s" failure transport was not found. Available transports are: "%s".',
                    $name,
                    implode('", "', $this->getFailureTransportNames())
                )
            );
        }

        return $this->failureTransports->get($name);
    }

    public function getFailureTransportNames(): array
    {
        return array_keys($this->failureTransports->getProvidedServices());
    }
}