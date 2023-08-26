<?php

namespace SoureCode\Bundle\Worker\Entity;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use SoureCode\Bundle\Worker\Command\WorkerCommand;
use SoureCode\Bundle\Worker\Repository\WorkerRepository;
use Symfony\Component\Clock\MonotonicClock;

#[ORM\Entity(repositoryClass: WorkerRepository::class)]
class Worker
{
    public static ?int $currentId = null;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column()]
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

    public function setReset(bool $reset): static
    {
        $this->reset = $reset;

        return $this;
    }

    public function isRunning(): bool
    {
        return $this->status !== WorkerStatus::OFFLINE;
    }

    public function getCommand()
    {
        $command = [
            WorkerCommand::getDefaultName(),
            '-vv',
            '--id',
            $this->getId(),
            "--no-interaction",
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

    public function addMemoryUsage(int $memoryUsage): void
    {
        $timeZone = new \DateTimeZone("UTC");
        $now = (new MonotonicClock())->withTimeZone($timeZone)->now();

        $this->memoryUsage[$now->format('Y-m-d\TH:i:s.u')] = $memoryUsage;

        // remove older than 1 hour
        $this->memoryUsage = array_filter($this->memoryUsage, static function ($key) use ($timeZone, $now) {
            $key = preg_replace('/\.\d+$/', '', $key);
            $date = new \DateTimeImmutable($key, $timeZone);
            $diff = $now->getTimestamp() - $date->getTimestamp();

            return $diff < 3600;
        }, ARRAY_FILTER_USE_KEY);
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

    public function incrementHandled(): int
    {
        $this->handled++;

        return $this->handled;
    }

    public function incrementFailed(): int
    {
        $this->failed++;

        return $this->failed;
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
}