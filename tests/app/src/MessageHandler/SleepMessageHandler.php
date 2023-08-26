<?php

namespace SoureCode\Bundle\Worker\Tests\app\src\MessageHandler;

use Psr\Log\LoggerInterface;
use SoureCode\Bundle\Worker\Tests\app\src\Message\SleepMessage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class SleepMessageHandler
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function __invoke(SleepMessage $message)
    {
        $this->logger->info("Sleeping for {$message->getSeconds()} seconds.");
        sleep($message->getSeconds());
        $this->logger->info("Done sleeping for {$message->getSeconds()} seconds.");

        return $message->getSeconds();
    }
}