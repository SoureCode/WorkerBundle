<?php

namespace SoureCode\Bundle\Worker\Tests\app\src\MessageHandler;

use Psr\Log\LoggerInterface;
use SoureCode\Bundle\Worker\Tests\app\src\Message\StopMessage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class StopMessageHandler
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function __invoke(StopMessage $message)
    {
        $this->logger->info('Worker should be stopped after this message.');
    }
}