<?php

namespace SoureCode\Bundle\Worker\Daemon;

use SoureCode\Bundle\Worker\Entity\Worker;

interface DumperInterface
{
    public function dump(Worker $worker): void;

    public function remove(Worker $worker): void;
}