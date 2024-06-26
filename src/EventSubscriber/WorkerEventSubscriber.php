<?php

namespace SoureCode\Bundle\Worker\EventSubscriber;

use Doctrine\ORM\Event\PreRemoveEventArgs;
use SoureCode\Bundle\Worker\Entity\Worker;
use SoureCode\Bundle\Worker\Manager\WorkerManager;

class WorkerEventSubscriber
{
    public function __construct(
        private readonly WorkerManager $workerManager,
    )
    {
    }

    public function preRemove(PreRemoveEventArgs $event): void
    {
        $object = $event->getObject();

        if ($object instanceof Worker) {
            $this->workerManager->stop($object);
        }
    }
}
