<?php

namespace SoureCode\Bundle\Worker\MessageHandler;

use SoureCode\Bundle\Worker\Manager\WorkerManager;
use SoureCode\Bundle\Worker\Message\StopWorkerMessage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class StopWorkerMessageHandler
{
    private WorkerManager $workerManager;

    public function __construct(WorkerManager $workerManager)
    {
        $this->workerManager = $workerManager;
    }

    public function __invoke(StopWorkerMessage $message)
    {
        $this->workerManager->stop($message->getId(), $message->getTimeout(), $message->getSignals());
    }
}