<?php

namespace SoureCode\Bundle\Worker\EventSubscriber;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use SoureCode\Bundle\Worker\Entity\Worker;
use SoureCode\Bundle\Worker\Entity\WorkerStatus;
use SoureCode\Bundle\Worker\Messenger\TrackingStamp;
use SoureCode\Bundle\Worker\Repository\WorkerRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Event\SendMessageToTransportsEvent;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;
use Symfony\Component\Messenger\Event\WorkerRunningEvent;
use Symfony\Component\Messenger\Event\WorkerStartedEvent;
use Symfony\Component\Messenger\Event\WorkerStoppedEvent;
use function class_exists;

class MessengerEventSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly LoggerInterface        $logger,
        private readonly ClockInterface         $clock,
        private readonly EntityManagerInterface $entityManager,
        private readonly WorkerRepository       $workerRepository,
    )
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            WorkerMessageReceivedEvent::class => ['onWorkerMessageReceived', 50],
            WorkerMessageHandledEvent::class => ['onWorkerMessageHandled', 50],
            WorkerMessageFailedEvent::class => ['onWorkerMessageFailed', 50],
            WorkerRunningEvent::class => ['onWorkerRunning', 50],
            WorkerStoppedEvent::class => ['onWorkerStopped', 50],
            WorkerStartedEvent::class => ['onWorkerStarted', 50],
            SendMessageToTransportsEvent::class => ['onSendMessageToTransports', 50],
        ];
    }

    public function onWorkerRunning(WorkerRunningEvent $event): void
    {
        $worker = $this->getWorker();

        if (null !== $worker) {
            $this->logger->debug('Worker running.', [
                'worker_id' => $worker->getId(),
                'is_worker_idle' => $event->isWorkerIdle(),
            ]);

            $now = $this->clock->now();
            $worker->setStatus($event->isWorkerIdle() ? WorkerStatus::IDLE : WorkerStatus::PROCESSING);
            $worker->setLastHeartbeat($now);

            $this->flush();
        }
    }

    private function getWorker(): ?Worker
    {
        if (null === Worker::$currentId) {
            $this->logger->warning('Worker id is not set, who is responsible for this?');
            return null;
        }

        $worker = $this->workerRepository->find(Worker::$currentId);

        if ($this->entityManager->isOpen()) {
            $this->entityManager->refresh($worker);
        } else {
            $this->logger->warning('Entity manager is closed, can not refresh worker.');
        }

        return $worker;
    }

    private function flush(): void
    {
        if ($this->entityManager->isOpen() && $this->entityManager->getConnection()->isConnected()) {
            $this->entityManager->flush();
        } else {
            $this->logger->warning('Entity manager is closed, can not flush.');
        }
    }

    public function onWorkerMessageReceived(WorkerMessageReceivedEvent $event): void
    {
        $envelope = $event->getEnvelope();
        $stamp = $envelope->last(TrackingStamp::class);

        if (class_exists('Symfony\\Component\\Scheduler\\Messenger\\ScheduledStamp') && $scheduledStamp = $envelope->last('Symfony\\Component\\Scheduler\\Messenger\\ScheduledStamp')) {
            // scheduler transport doesn't trigger SendMessageToTransportsEvent
            $stamp = new TrackingStamp(Worker::$currentId, $scheduledStamp->messageContext->triggeredAt);
        }

        if ($stamp instanceof TrackingStamp) {
            $stamp->markReceived(Worker::$currentId, $event->getReceiverName());
        }

        $worker = $this->getWorker();

        if (null !== $worker) {
            $this->logger->debug('Worker message received.', [
                'worker_id' => $worker->getId(),
                'envelope' => $event->getEnvelope(),
                'transport' => $event->getReceiverName(),
            ]);

            $worker->setLastHeartbeat($this->clock->now());
            $worker->setStatus(WorkerStatus::PROCESSING);

            $this->flush();
        }
    }

    public function onWorkerMessageHandled(WorkerMessageHandledEvent $event): void
    {
        if (!$stamp = $event->getEnvelope()->last(TrackingStamp::class)) {
            return;
        }

        if (!$stamp->isReceived()) {
            return;
        }

        $stamp->markFinished();

        $worker = $this->getWorker();

        if (null !== $worker) {
            $this->logger->debug('Worker message handled.', [
                'worker_id' => $worker->getId(),
                'envelope' => $event->getEnvelope(),
                'transport' => $event->getReceiverName(),
            ]);

            $worker->setLastHeartbeat($this->clock->now());
            $worker->incrementHandled();
        }

        $this->flush();
    }

    public function onWorkerMessageFailed(WorkerMessageFailedEvent $event): void
    {
        if (!$stamp = $event->getEnvelope()->last(TrackingStamp::class)) {
            return;
        }

        if (!$stamp->isReceived()) {
            return;
        }

        $stamp->markFinished();

        $worker = $this->getWorker();

        if (null !== $worker) {
            $this->logger->debug('Worker message failed.', [
                'worker_id' => $worker->getId(),
                'envelope' => $event->getEnvelope(),
                'exception' => $event->getThrowable(),
                'transport' => $event->getReceiverName(),
                'will_retry' => $event->willRetry(),
            ]);

            $worker->setLastHeartbeat($this->clock->now());
            $worker->incrementFailed();

            $this->flush();
        }
    }

    public function onWorkerStopped(WorkerStoppedEvent $event): void
    {
        $worker = $this->getWorker();

        if (null !== $worker) {
            $this->logger->debug('Worker stopped.', [
                'worker_id' => $worker->getId(),
            ]);

            $worker->setStatus(WorkerStatus::OFFLINE);
            $worker->setStartedAt(null);
            $worker->setLastHeartbeat(null);

            $this->flush();
        }
    }

    public function onWorkerStarted(WorkerStartedEvent $event): void
    {
        $worker = $this->getWorker();

        if (null !== $worker) {
            $this->logger->debug('Worker started.', [
                'worker_id' => $worker->getId(),
            ]);

            // @todo test if flush and the middleware named "doctrine_transaction" works
            //       otherwise, write manually to database over the entity manager?

            $now = $this->clock->now();
            $worker->setStatus(WorkerStatus::IDLE);
            $worker->setStartedAt($now);
            $worker->setLastHeartbeat($now);

            $this->flush();
        }
    }

    public function onSendMessageToTransports(SendMessageToTransportsEvent $event): void
    {
        $event->setEnvelope(
            $event->getEnvelope()
                ->with(new TrackingStamp(Worker::$currentId, $this->clock->now()))
        );
    }
}