<?php

namespace SoureCode\Bundle\Worker\Message;

class StopWorkerMessage
{
    private int $id;
    private ?int $timeout;
    private ?array $signals = null;

    public function __construct(int $id, ?int $timeout = null, ?array $signals = null)
    {
        $this->id = $id;
        $this->timeout = $timeout;
        $this->signals = $signals;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getTimeout(): ?int
    {
        return $this->timeout;
    }

    public function getSignals(): ?array
    {
        return $this->signals;
    }
}