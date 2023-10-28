<?php

namespace SoureCode\Bundle\Worker\Tests;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use SoureCode\Bundle\Worker\Entity\MessengerMessage;
use SoureCode\Bundle\Worker\Entity\Worker;
use SoureCode\Bundle\Worker\Entity\WorkerStatus;
use SoureCode\Bundle\Worker\Manager\WorkerManager;
use SoureCode\Bundle\Worker\Repository\MessengerMessageRepository;
use SoureCode\Bundle\Worker\Repository\WorkerRepository;
use SoureCode\Bundle\Worker\Tests\app\src\Message\SleepMessage;
use Symfony\Component\Messenger\MessageBusInterface;

class WorkerTest extends AbstractBaseTest
{
    private ?WorkerRepository $workerRepository = null;
    private ?EntityManagerInterface $entityManager = null;
    private ?MessageBusInterface $messageBus = null;
    private ?MessengerMessageRepository $messengerMessageRepository = null;
    private ?WorkerManager $workerManager = null;

    public function testWorkerStart(): void
    {
        $worker = new Worker();
        $worker->setTransports([
            'async',
        ]);

        $this->entityManager->persist($worker);
        $this->entityManager->flush();

        try {
            $this->assertEquals(WorkerStatus::OFFLINE, $worker->getStatus(), 'Worker is offline');
            $this->assertSame(0, $worker->getHandled(), 'No message has been handled');
            $this->assertSame(0, $worker->getFailed(), 'No message has been failed');
            $this->assertEmpty($this->messengerMessageRepository->findAll(), 'No message has been dispatched');

            self::assertTrue($this->workerManager->stop($worker));
            self::assertTrue($this->workerManager->start($worker));

            self::assertTrue($this->workerManager->isRunning($worker));

            $this->messageBus->dispatch(new SleepMessage(2));

            $this->waitUntil(function () use ($worker) {
                $this->entityManager->refresh($worker);

                return $worker->getStatus() === WorkerStatus::PROCESSING;
            });

            $this->assertNotEmpty($this->messengerMessageRepository->findAll(), 'Message has been dispatched');
            $this->assertEquals(WorkerStatus::PROCESSING, $worker->getStatus(), 'Worker is processing');
            $this->assertSame(0, $worker->getHandled(), 'No message has been handled');
            $this->assertSame(0, $worker->getFailed(), 'No message has been failed');

            $this->waitUntil(function () use ($worker) {
                $this->entityManager->refresh($worker);

                return $worker->getStatus() === WorkerStatus::IDLE;
            });

            self::assertTrue($this->workerManager->stop($worker));

            // refresh again, as the worker changed the status to offline
            $this->entityManager->refresh($worker);

            $this->assertEmpty($this->messengerMessageRepository->findAll(), 'All messages has been handled');
            $this->assertEquals(WorkerStatus::OFFLINE, $worker->getStatus(), 'Worker is offline');
            $this->assertSame(1, $worker->getHandled(), 'All messages has been handled');
            $this->assertSame(0, $worker->getFailed(), 'No message has been failed');
        } finally {
            // ensures that the worker is stopped, even if the test fails
            self::assertTrue($this->workerManager->stop($worker));
        }
    }

    protected function setUp(): void
    {
        parent::setUp();

        $container = self::getContainer();

        $this->workerRepository = $container->get(WorkerRepository::class);
        $this->messengerMessageRepository = $container->get(MessengerMessageRepository::class);
        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->messageBus = $container->get(MessageBusInterface::class);
        $this->workerManager = $container->get(WorkerManager::class);

        $schemaTool = new SchemaTool($this->entityManager);
        $schemaTool->updateSchema([
            $this->entityManager->getClassMetadata(Worker::class),
            $this->entityManager->getClassMetadata(MessengerMessage::class),
        ]);

        $this->workerRepository->createQueryBuilder('w')
            ->delete()
            ->getQuery()
            ->execute();

        $this->messengerMessageRepository->createQueryBuilder('m')
            ->delete()
            ->getQuery()
            ->execute();
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
        $this->workerManager = null;
    }
}