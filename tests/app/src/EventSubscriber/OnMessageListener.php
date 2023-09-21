<?php

namespace SoureCode\Bundle\Worker\Tests\app\src\EventSubscriber;

use SoureCode\Bundle\Worker\Tests\app\src\Message\StopMessage;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\Event\WorkerRunningEvent;

class OnMessageListener implements EventSubscriberInterface
{
    private ?string $lastMessageClass = null;

    public static function getSubscribedEvents(): array
    {
        return [
            WorkerMessageHandledEvent::class => 'onWorkerMessageHandled',
            WorkerRunningEvent::class => 'onWorkerRunning',
        ];
    }

    public function onWorkerMessageHandled(WorkerMessageHandledEvent $event): void
    {
        $this->lastMessageClass = $event->getEnvelope()->getMessage()::class;
    }

    public function onWorkerRunning(WorkerRunningEvent $event): void
    {
        if ($event->isWorkerIdle()) {
            if ($this->lastMessageClass === StopMessage::class) {
                $event->getWorker()->stop();
            }
        }
    }
}
