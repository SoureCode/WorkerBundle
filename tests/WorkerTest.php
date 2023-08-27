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

class WorkerTest extends AbstractBaseTest
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

    public function testWorkerStart(): void
    {
        $worker = new Worker();
        $worker->setTransports([
            'async',
        ]);

        $this->entityManager->persist($worker);
        $this->entityManager->flush();

        $workerId = $worker->getId();

        try {
            $this->assertEquals(WorkerStatus::OFFLINE, $worker->getStatus(), 'Worker is offline');
            $this->assertSame(0, $worker->getHandled(), 'No message has been handled');
            $this->assertSame(0, $worker->getFailed(), 'No message has been failed');
            $this->assertCount(0, $worker->getMemoryUsage(), 'Memory usage has not been collected');

            $this->workerManager->start($workerId);

            $this->messageBus->dispatch(new SleepMessage(2));

            $this->waitUntil(function () use ($worker) {
                $this->entityManager->refresh($worker);

                return $worker->getStatus() === WorkerStatus::PROCESSING;
            });

            $this->assertEquals(WorkerStatus::PROCESSING, $worker->getStatus(), 'Worker is processing');
            $this->assertSame(0, $worker->getHandled(), 'No message has been handled');
            $this->assertSame(0, $worker->getFailed(), 'No message has been failed');
            $this->assertTrue(count($worker->getMemoryUsage()) > 0, 'Memory usage has been collected');

            $this->waitUntil(function () use ($worker) {
                $this->entityManager->refresh($worker);

                return $worker->getStatus() === WorkerStatus::IDLE;
            });

            $this->workerManager->stop($workerId);

            // refresh again, as the worker changed the status to offline
            $this->entityManager->refresh($worker);

            $this->assertEquals(WorkerStatus::OFFLINE, $worker->getStatus(), 'Worker is offline');
            $this->assertSame(1, $worker->getHandled(), 'All messages has been handled');
            $this->assertSame(0, $worker->getFailed(), 'No message has been failed');
            $this->assertTrue(count($worker->getMemoryUsage()) > 0, 'Memory usage has been collected');
        } finally {
            // ensures that the worker is stopped, even if the test fails
            $this->workerManager->stop($worker->getId());
        }
    }

    private function waitUntil(\Closure $closure, int $timeout = 10): void
    {
        $iteration = 0;

        while (true) {
            if ($closure()) {
                return;
            }

            if ($iteration > $timeout) {
                throw new \RuntimeException('Timeout');
            }

            $iteration++;
            sleep(1);
        }
    }
}