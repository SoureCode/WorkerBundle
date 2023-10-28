<?php

namespace SoureCode\Bundle\Worker\Tests;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use SoureCode\Bundle\Worker\Entity\Worker;
use SoureCode\Bundle\Worker\Entity\WorkerStatus;
use SoureCode\Bundle\Worker\Manager\WorkerManager;
use SoureCode\Bundle\Worker\Repository\MessengerMessageRepository;
use SoureCode\Bundle\Worker\Repository\WorkerRepository;
use SoureCode\Bundle\Worker\Tests\app\src\Message\SleepMessage;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

class WorkerManagerTest extends AbstractBaseTest
{
    private ?EntityManagerInterface $entityManager = null;
    private ?MessageBusInterface $messageBus = null;
    private ?MessengerMessageRepository $messengerMessageRepository = null;
    private ?WorkerManager $workerManager = null;

    public function testManagerCompilerPass(): void
    {
        $this->assertEquals([
            'messenger.bus.default',
            'messenger.bus.high'
        ], $this->workerManager->getBusIds());

        $this->assertEquals([
            'async',
            'async_high',
            'failed',
            'failed_high',
            'sync',
        ], $this->workerManager->getReceiverNames());

        $this->assertEquals([
            'failed',
            'failed_high',
        ], $this->workerManager->getFailureTransportNames());

        $this->assertSame("failed", $this->workerManager->getGlobalFailureReceiverName());
    }

    public function testDontFailIfNoWorkerIsRunning(): void
    {
        // Arrange
        $worker = new Worker();
        $worker->setTransports([
            'async',
        ]);

        $this->entityManager->persist($worker);
        $this->entityManager->flush();

        // Act & Assert
        self::assertTrue($this->workerManager->stopAll());
    }

    public function testGracefullyStop(): void
    {
        // Arrange
        $worker = new Worker();
        $worker->setTransports([
            'async',
        ]);

        $this->entityManager->persist($worker);
        $this->entityManager->flush();

        try {
            // Act
            // Ensure all are stopped
            self::assertTrue($this->workerManager->stopAll());
            self::assertTrue($this->workerManager->startAll());

            // wait until worker is idle (online)
            $this->waitUntil(function () use ($worker) {
                $this->entityManager->refresh($worker);

                return $worker->getStatus() === WorkerStatus::IDLE;
            });

            $this->messageBus->dispatch(new SleepMessage(2));
            $this->messageBus->dispatch(new SleepMessage(2));
            $this->messageBus->dispatch(new SleepMessage(2));

            // wait a bit until the worker is processing
            $this->waitUntil(function () use ($worker) {
                $this->entityManager->refresh($worker);

                return $worker->getStatus() === WorkerStatus::PROCESSING;
            });

            // Act
            self::assertTrue($this->workerManager->stop($worker));

            // Assert
            self::assertSame(2, $this->messengerMessageRepository->count([]), "There should be 2 messages left.");
            self::assertFalse($this->workerManager->isRunning($worker), 'Worker should be stopped.');

            $this->entityManager->refresh($worker);
            self::assertSame(WorkerStatus::OFFLINE, $worker->getStatus(), 'Worker should be offline.');
        } finally {
            // Cleanup
            $this->workerManager->stopAll();
        }
    }

    protected function setUp(): void
    {
        parent::setUp();

        $container = self::getContainer();

        $this->messengerMessageRepository = $container->get(MessengerMessageRepository::class);
        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->messageBus = $container->get(MessageBusInterface::class);
        $this->workerManager = $container->get(WorkerManager::class);

        $schemaTool = new SchemaTool($this->entityManager);
        $schemaTool->updateSchema([
            $this->entityManager->getClassMetadata(Worker::class),
        ]);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $schemaTool = new SchemaTool($this->entityManager);
        $schemaTool->dropDatabase();

        $this->entityManager->getConnection()->close();
        $this->entityManager->clear();
        $this->entityManager->close();

        $this->messengerMessageRepository = null;
        $this->entityManager = null;
        $this->messageBus = null;
        $this->workerManager = null;
    }
}