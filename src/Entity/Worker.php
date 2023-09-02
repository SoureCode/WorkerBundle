<?php

namespace SoureCode\Bundle\Worker\Entity;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use SoureCode\Bundle\Worker\Command\WorkerCommand;

#[ORM\Entity]
class Worker
{
    public static ?int $currentId = null;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private array $transports = [];

    #[ORM\Column]
    private ?int $messageLimit = 0;

    #[ORM\Column]
    private ?int $failureLimit = 0;

    #[ORM\Column]
    private ?int $memoryLimit = 0;

    #[ORM\Column]
    private ?int $timeLimit = 0;

    #[ORM\Column]
    private ?int $sleep = 1;

    #[ORM\Column(type: Types::JSON)]
    private array $queues = [];

    #[ORM\Column]
    private ?bool $reset = true;

    #[ORM\Column(enumType: WorkerStatus::class)]
    private WorkerStatus $status = WorkerStatus::OFFLINE;

    #[ORM\Column]
    private array $memoryUsage = [];

    #[ORM\Column]
    private ?int $handled = 0;

    #[ORM\Column]
    private ?int $failed = 0;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $startedAt = null;

    #[ORM\Column]
    private ?bool $shouldExit = false;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $lastHeartbeat = null;

    public function setReset(bool $reset): static
    {
        $this->reset = $reset;

        return $this;
    }

    public function isRunning(): bool
    {
        return $this->status !== WorkerStatus::OFFLINE;
    }

    public function getCommand(): array
    {
        $command = [
            WorkerCommand::getDefaultName(),
            '-vv',
            '--no-debug',
            "--no-interaction",
            '--id',
            $this->getId(),
        ];

        if ($this->getMessageLimit() > 0) {
            $command[] = "--limit=" . $this->getMessageLimit();
        }

        if ($this->getFailureLimit() > 0) {
            $command[] = "--failure-limit=" . $this->getFailureLimit();
        }

        if ($this->getMemoryLimit() > 0) {
            $command[] = "--memory-limit=" . $this->getMemoryLimit();
        }

        if ($this->getTimeLimit() > 0) {
            $command[] = "--time-limit=" . $this->getTimeLimit();
        }

        if ($this->getSleep() > 1) {
            $command[] = "--sleep=" . $this->getSleep();
        }

        if (!$this->isReset()) {
            $command[] = "--no-reset";
        }

        foreach ($this->getQueues() as $queue) {
            $command[] = "--queue=" . $queue;
        }

        foreach ($this->getTransports() as $transport) {
            $command[] = $transport;
        }

        return $command;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMessageLimit(): ?int
    {
        return $this->messageLimit;
    }

    public function setMessageLimit(?int $messageLimit): static
    {
        $this->messageLimit = $messageLimit;

        return $this;
    }

    public function getFailureLimit(): ?int
    {
        return $this->failureLimit;
    }

    public function setFailureLimit(int $failureLimit): static
    {
        $this->failureLimit = $failureLimit;

        return $this;
    }

    public function getMemoryLimit(): ?int
    {
        return $this->memoryLimit;
    }

    public function setMemoryLimit(int $memoryLimit): static
    {
        $this->memoryLimit = $memoryLimit;

        return $this;
    }

    public function getTimeLimit(): ?int
    {
        return $this->timeLimit;
    }

    public function setTimeLimit(int $timeLimit): static
    {
        $this->timeLimit = $timeLimit;

        return $this;
    }

    public function getSleep(): ?int
    {
        return $this->sleep;
    }

    public function setSleep(int $sleep): static
    {
        $this->sleep = $sleep;

        return $this;
    }

    public function isReset(): ?bool
    {
        return $this->reset;
    }

    public function getQueues(): array
    {
        return $this->queues;
    }

    public function setQueues(array $queues): static
    {
        $this->queues = $queues;

        return $this;
    }

    public function getTransports(): array
    {
        return $this->transports;
    }

    public function setTransports(array $transports): static
    {
        $this->transports = $transports;

        return $this;
    }

    public function getStatus(): WorkerStatus
    {
        return $this->status;
    }

    public function setStatus(WorkerStatus $status): Worker
    {
        $this->status = $status;

        return $this;
    }

    public function getMemoryUsage(): array
    {
        return $this->memoryUsage;
    }

    public function setMemoryUsage(array $memoryUsage): void
    {
        $this->memoryUsage = $memoryUsage;
    }

    public function getStartedAt(): ?DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function setStartedAt(?DateTimeImmutable $startedAt): Worker
    {
        $this->startedAt = $startedAt;

        return $this;
    }

    public function getHandled(): ?int
    {
        return $this->handled;
    }

    public function setHandled(?int $handled): void
    {
        $this->handled = $handled;
    }

    public function getFailed(): ?int
    {
        return $this->failed;
    }

    public function setFailed(?int $failed): void
    {
        $this->failed = $failed;
    }

    public function getShouldExit(): ?bool
    {
        return $this->shouldExit;
    }

    public function setShouldExit(?bool $shouldExit): self
    {
        $this->shouldExit = $shouldExit;

        return $this;
    }

    public function getLastHeartbeat(): ?DateTimeImmutable
    {
        return $this->lastHeartbeat;
    }

    public function setLastHeartbeat(?DateTimeImmutable $lastHeartbeat): void
    {
        $this->lastHeartbeat = $lastHeartbeat;
    }

    public function onWorkerStarted(DateTimeImmutable $timestamp): void
    {
        $this->online($timestamp);
        $this->addMemoryUsage($timestamp);
    }

    private function online(DateTimeImmutable $timestamp): void
    {
        $this->setStatus(WorkerStatus::IDLE);
        $this->setShouldExit(false);
        $this->setStartedAt($timestamp);
        $this->setLastHeartbeat($timestamp);
    }

    public function addMemoryUsage(DateTimeImmutable $timestamp): void
    {
        $memoryUsage = memory_get_usage(true);
        $key = $timestamp->format('Y-m-d\TH:i:s');

        if (array_key_exists($key, $this->memoryUsage)) {
            $this->memoryUsage[$key] = max(
                $this->memoryUsage[$key],
                $memoryUsage
            );
        } else {
            $this->memoryUsage[$key] = $memoryUsage;
        }

        // remove older than 1 hour
        $this->memoryUsage = array_filter($this->memoryUsage, static function ($key) use ($timestamp) {
            $date = new DateTimeImmutable($key);
            $diff = $timestamp->getTimestamp() - $date->getTimestamp();

            return $diff < 3600;
        }, ARRAY_FILTER_USE_KEY);
    }

    public function onWorkerStopped(DateTimeImmutable $timestamp): void
    {
        $this->offline();
        $this->addMemoryUsage($timestamp);
    }

    public function offline(): void
    {
        $this->setStatus(WorkerStatus::OFFLINE);
        $this->setStartedAt(null);
        $this->setShouldExit(false);
        $this->setLastHeartbeat(null);
    }

    public function onWorkerMessageFailed(DateTimeImmutable $timestamp): void
    {
        $this->setLastHeartbeat($timestamp);
        $this->incrementFailed();
        $this->addMemoryUsage($timestamp);
    }

    public function incrementFailed(): int
    {
        $this->failed++;

        return $this->failed;
    }

    public function onWorkerMessageHandled(DateTimeImmutable $timestamp): void
    {
        $this->setLastHeartbeat($timestamp);
        $this->incrementHandled();
        $this->addMemoryUsage($timestamp);
    }

    public function incrementHandled(): int
    {
        $this->handled++;

        return $this->handled;
    }

    public function onWorkerMessageReceived(DateTimeImmutable $timestamp): void
    {
        $this->setLastHeartbeat($timestamp);
        $this->setStatus(WorkerStatus::PROCESSING);
        $this->addMemoryUsage($timestamp);
    }

    public function onWorkerRunning(DateTimeImmutable $timestamp): void
    {
        $this->setLastHeartbeat($timestamp);
        $this->addMemoryUsage($timestamp);
    }
}
