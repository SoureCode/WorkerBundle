<?php

namespace SoureCode\Bundle\Worker\EventSubscriber;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use SoureCode\Bundle\Worker\Entity\MessengerMessage;
use SoureCode\Bundle\Worker\Entity\Worker;
use SoureCode\Bundle\Worker\Entity\WorkerStatus;
use SoureCode\Bundle\Worker\Messenger\TrackingStamp;
use SoureCode\Bundle\Worker\Repository\MessengerMessageRepository;
use SoureCode\Bundle\Worker\Repository\WorkerRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\DoctrineReceivedStamp;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\SendMessageToTransportsEvent;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;
use Symfony\Component\Messenger\Event\WorkerRunningEvent;
use Symfony\Component\Messenger\Event\WorkerStartedEvent;
use Symfony\Component\Messenger\Event\WorkerStoppedEvent;
use Symfony\Component\Messenger\Exception\LogicException;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

class MessengerEventSubscriber implements EventSubscriberInterface
{
    private ClockInterface $clock;
    private LoggerInterface $logger;
    private EntityManagerInterface $entityManager;
    private WorkerRepository $workerRepository;
    private MessengerMessageRepository $messengerMessageRepository;
    private SerializerInterface $serializer;

    public function __construct(
        ClockInterface             $clock,
        LoggerInterface            $logger,
        EntityManagerInterface     $entityManager,
        WorkerRepository           $workerRepository,
        MessengerMessageRepository $messengerMessageRepository,
        SerializerInterface        $serializer,
    )
    {
        $this->clock = $clock;
        $this->logger = $logger;
        $this->entityManager = $entityManager;
        $this->workerRepository = $workerRepository;
        $this->messengerMessageRepository = $messengerMessageRepository;
        $this->serializer = $serializer;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            WorkerMessageReceivedEvent::class => 'onWorkerMessageReceived',
            WorkerMessageHandledEvent::class => 'onWorkerMessageHandled',
            WorkerMessageFailedEvent::class => 'onWorkerMessageFailed',
            WorkerRunningEvent::class => 'onWorkerRunning',
            WorkerStoppedEvent::class => 'onWorkerStopped',
            WorkerStartedEvent::class => 'onWorkerStarted',
            SendMessageToTransportsEvent::class => 'onSendMessageToTransports',
        ];
    }

    public function onWorkerRunning(WorkerRunningEvent $event): void
    {
        $worker = $this->getWorker();

        if (null !== $worker) {
            if ($event->isWorkerIdle() && $worker->getShouldExit()) {
                $event->getWorker()->stop();
                $worker->setShouldExit(false);
                $worker->setStatus(WorkerStatus::OFFLINE);
            } else {
                $worker->setStatus($event->isWorkerIdle() ? WorkerStatus::IDLE : WorkerStatus::PROCESSING);
            }

            $worker->addMemoryUsage();

            $this->entityManager->flush();
        }
    }

    public function onWorkerMessageReceived(WorkerMessageReceivedEvent $event): void
    {
        $envelope = $event->getEnvelope();
        $stamp = $envelope->last(TrackingStamp::class);

        if (\class_exists('Symfony\\Component\\Scheduler\\Messenger\\ScheduledStamp') && $scheduledStamp = $envelope->last('Symfony\\Component\\Scheduler\\Messenger\\ScheduledStamp')) {
            // scheduler transport doesn't trigger SendMessageToTransportsEvent
            $stamp = new TrackingStamp(Worker::$currentId, $scheduledStamp->messageContext->triggeredAt);
        }

        if ($stamp instanceof TrackingStamp) {
            $stamp->markReceived(Worker::$currentId, $event->getReceiverName());
        }

        $worker = $this->getWorker();

        if (null !== $worker) {
            $worker->setStatus(WorkerStatus::PROCESSING);
            $worker->addMemoryUsage();

            $this->entityManager->flush();
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

        $envelope = $event->getEnvelope();

        $doctrineReceivedStamp = $this->findDoctrineReceivedStamp($envelope);

        if (null !== $doctrineReceivedStamp) {
            $originalMessage = $this->messengerMessageRepository->find($doctrineReceivedStamp->getId());

            if (null === $originalMessage) {
                throw new LogicException('No message found with id: ' . $doctrineReceivedStamp->getId());
            }

            $encodedMessage = $this->serializer->encode($envelope);

            $message = new MessengerMessage();
            $message->setHeaders($encodedMessage['headers'] ?? '[]');
            $message->setBody($encodedMessage['body']);
            $message->setCreatedAt($originalMessage->getCreatedAt());
            $message->setDeliveredAt($originalMessage->getDeliveredAt());
            $message->setAvailableAt($originalMessage->getAvailableAt());
            $message->setQueueName('history');

            $this->entityManager->persist($message);
            $this->entityManager->flush();
        }

        $worker = $this->getWorker();

        if (null !== $worker) {
            $worker->incrementHandled();
            $worker->addMemoryUsage();

            $this->entityManager->flush();
        }
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
            $worker->incrementFailed();
            $worker->addMemoryUsage();

            $this->entityManager->flush();
        }
    }

    public function onWorkerStopped(WorkerStoppedEvent $event): void
    {
        $worker = $this->getWorker();

        if (null !== $worker) {
            $worker->setStatus(WorkerStatus::OFFLINE);
            $worker->setStartedAt(null);
            $worker->setShouldExit(false);
            $worker->addMemoryUsage();

            $this->entityManager->flush();
        }
    }

    public function onWorkerStarted(WorkerStartedEvent $event): void
    {
        $worker = $this->getWorker();

        if (null !== $worker) {
            // @todo test if flush and the middleware named "doctrine_transaction" works
            //       otherwise, write manually to database over the entity manager?

            $worker->setStatus(WorkerStatus::IDLE);
            $worker->setStartedAt($this->clock->now());
            $worker->addMemoryUsage();

            $this->entityManager->flush();
        }
    }

    private function getWorker(): ?Worker
    {
        if (null === Worker::$currentId) {
            return null;
        }

        return $this->workerRepository->find(Worker::$currentId);
    }

    public function onSendMessageToTransports(SendMessageToTransportsEvent $event): void
    {
        $event->setEnvelope(
            $event->getEnvelope()
                ->with(new TrackingStamp())
        );
    }

    private function findDoctrineReceivedStamp(Envelope $envelope): ?DoctrineReceivedStamp
    {
        /** @var DoctrineReceivedStamp|null $doctrineReceivedStamp */
        $doctrineReceivedStamp = $envelope->last(DoctrineReceivedStamp::class);

        if (null === $doctrineReceivedStamp) {
            return null;
        }

        return $doctrineReceivedStamp;
    }
}