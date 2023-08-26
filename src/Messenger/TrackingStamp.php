<?php

namespace SoureCode\Bundle\Worker\Messenger;

use DateTimeImmutable;
use DateTimeInterface;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\MonotonicClock;
use Symfony\Component\Messenger\Stamp\StampInterface;

class TrackingStamp implements StampInterface
{
    private ?int $workerId = null;
    private ?DateTimeImmutable $dispatchedAt;
    private ?DateTimeImmutable $receivedAt = null;
    private ?DateTimeImmutable $finishedAt = null;
    private array $memoryUsage = [];
    private ?string $transport = null;

    public function __construct(
        ?string $workerId = null,
        ?DateTimeImmutable $dispatchedAt = null,
    )
    {
        $this->workerId = $workerId;
        $this->dispatchedAt = $dispatchedAt;

        $now = (new MonotonicClock())->now();

        $this->memoryUsage[$now->format('Y-m-d\TH:i:s.u')] = memory_get_usage(true);
    }

    public function markReceived(?int $workerId, string $transport): self
    {
        $now = (new MonotonicClock())->now();

        $this->workerId = $workerId;
        $this->transport = $transport;
        $this->receivedAt = $now;
        $this->memoryUsage[$now->format('Y-m-d\TH:i:s.u')] = memory_get_usage(true);

        return $this;
    }

    public function markFinished(): self
    {
        if (!$this->isReceived()) {
            throw new \LogicException('Message was not received.');
        }

        $now = (new MonotonicClock())->now();

        $this->finishedAt = $now;
        $this->memoryUsage[$now->format('Y-m-d\TH:i:s.u')] = memory_get_usage(true);

        return $this;
    }

    public function getWorkerId(): string
    {
        return $this->workerId;
    }

    public function getDispatchedAt(): ?DateTimeImmutable
    {
        return $this->dispatchedAt;
    }

    public function getReceivedAt(): ?DateTimeImmutable
    {
        return $this->receivedAt;
    }

    public function getFinishedAt(): ?DateTimeImmutable
    {
        return $this->finishedAt;
    }

    public function getMemoryUsage(): array
    {
        return $this->memoryUsage;
    }

    public function getTransport(): ?string
    {
        return $this->transport;
    }

    public function isReceived(): bool
    {
        return null !== $this->receivedAt;
    }

    public function isFinished(): bool
    {
        return null !== $this->finishedAt;
    }
}