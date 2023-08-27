<?php

namespace SoureCode\Bundle\Worker\Tests;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use SoureCode\Bundle\Worker\Entity\Worker;
use SoureCode\Bundle\Worker\Manager\WorkerManager;
use SoureCode\Bundle\Worker\Repository\MessengerMessageRepository;
use SoureCode\Bundle\Worker\Repository\WorkerRepository;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

class WorkerManagerTest extends AbstractBaseTest
{
    private ?WorkerRepository $workerRepository = null;
    private ?EntityManagerInterface $entityManager = null;
    private ?MessageBusInterface $messageBus = null;
    private ?MessengerMessageRepository $messengerMessageRepository = null;
    private ?SerializerInterface $serializer;
    private ?WorkerManager $workerManager = null;

    protected function setUp(): void
    {
        parent::setUp();

        $container = self::getContainer();

        $this->workerRepository = $container->get(WorkerRepository::class);
        $this->messengerMessageRepository = $container->get(MessengerMessageRepository::class);
        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->messageBus = $container->get(MessageBusInterface::class);
        $this->serializer = $container->get(SerializerInterface::class);
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

        $this->workerRepository = null;
        $this->messengerMessageRepository = null;
        $this->entityManager = null;
        $this->messageBus = null;
        $this->serializer = null;
        $this->workerManager = null;
    }


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

        // Act
        $result = $this->workerManager->stopAll();

        // Assert
        $this->assertSame(0, $result);
    }
}