<?php

namespace SoureCode\Bundle\Worker\EventSubscriber;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use SoureCode\Bundle\Daemon\Manager\DaemonManager;
use SoureCode\Bundle\Worker\Entity\MessengerMessage;
use SoureCode\Bundle\Worker\Entity\Worker;
use SoureCode\Bundle\Worker\Entity\WorkerStatus;
use SoureCode\Bundle\Worker\Manager\WorkerManager;
use SoureCode\Bundle\Worker\Messenger\TrackingStamp;
use SoureCode\Bundle\Worker\Repository\MessengerMessageRepository;
use SoureCode\Bundle\Worker\Repository\WorkerRepository;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
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
use function class_exists;

#[Autoconfigure(tags: ['monolog.logger' => ['channel' => 'worker']])]
class MessengerEventSubscriber implements EventSubscriberInterface
{
    private ClockInterface $clock;
    private LoggerInterface $logger;
    private EntityManagerInterface $entityManager;
    private WorkerRepository $workerRepository;
    private MessengerMessageRepository $messengerMessageRepository;
    private SerializerInterface $serializer;
    private DaemonManager $daemonManager;

    public function __construct(
        ClockInterface             $clock,
        LoggerInterface            $logger,
        EntityManagerInterface     $entityManager,
        WorkerRepository           $workerRepository,
        MessengerMessageRepository $messengerMessageRepository,
        SerializerInterface        $serializer,
        DaemonManager              $daemonManager,
    )
    {
        $this->clock = $clock;
        $this->logger = $logger;
        $this->entityManager = $entityManager;
        $this->workerRepository = $workerRepository;
        $this->messengerMessageRepository = $messengerMessageRepository;
        $this->serializer = $serializer;
        $this->daemonManager = $daemonManager;
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

                // prevent auto restart from daemon.
                $daemonId = WorkerManager::getDaemonId($worker->getId());
                $pid = $this->daemonManager->pid($daemonId);
                $pid->dumpExitFile();
            } else {
                $worker->setStatus($event->isWorkerIdle() ? WorkerStatus::IDLE : WorkerStatus::PROCESSING);
            }

            $worker->onWorkerRunning($this->clock->now());

            $this->entityManager->flush();
        }
    }

    private function getWorker(): ?Worker
    {
        if (null === Worker::$currentId) {
            return null;
        }

        $worker = $this->workerRepository->find(Worker::$currentId);

        $this->entityManager->refresh($worker);

        return $worker;
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
            $worker->onWorkerMessageReceived($this->clock->now());

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
        }

        $worker = $this->getWorker();
        $worker?->onWorkerMessageHandled($this->clock->now());

        $this->entityManager->flush();
    }

    private function findDoctrineReceivedStamp(Envelope $envelope): ?DoctrineReceivedStamp
    {
        /** @var DoctrineReceivedStamp|null $doctrineReceivedStamp */
        $doctrineReceivedStamp = $envelope->last(DoctrineReceivedStamp::class);

        return $doctrineReceivedStamp ?? null;
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
            $worker->onWorkerMessageFailed($this->clock->now());

            $this->entityManager->flush();
        }
    }

    public function onWorkerStopped(WorkerStoppedEvent $event): void
    {
        $worker = $this->getWorker();

        if (null !== $worker) {
            $worker->onWorkerStopped($this->clock->now());

            $this->entityManager->flush();
        }
    }

    public function onWorkerStarted(WorkerStartedEvent $event): void
    {
        $worker = $this->getWorker();

        if (null !== $worker) {
            // @todo test if flush and the middleware named "doctrine_transaction" works
            //       otherwise, write manually to database over the entity manager?
            $worker->onWorkerStarted($this->clock->now());

            $this->entityManager->flush();
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