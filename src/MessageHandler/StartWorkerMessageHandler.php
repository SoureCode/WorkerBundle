<?php

namespace SoureCode\Bundle\Worker\MessageHandler;

use SoureCode\Bundle\Worker\Manager\WorkerManager;
use SoureCode\Bundle\Worker\Message\StartWorkerMessage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class StartWorkerMessageHandler
{
    private WorkerManager $workerManager;

    public function __construct(WorkerManager $workerManager)
    {
        $this->workerManager = $workerManager;
    }

    public function __invoke(StartWorkerMessage $message): void
    {
        $this->workerManager->start($message->getId());
    }
}