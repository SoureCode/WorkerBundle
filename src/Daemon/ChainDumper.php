<?php

namespace SoureCode\Bundle\Worker\Daemon;

use SoureCode\Bundle\Worker\Entity\Worker;

class ChainDumper implements DumperInterface
{
    protected iterable $dumpers;

    /**
     * @param iterable<DumperInterface> $dumpers
     */
    public function __construct(iterable $dumpers)
    {
        $this->dumpers = $dumpers;
    }

    public function dump(Worker $worker): void
    {
        foreach ($this->dumpers as $dumper) {
            $dumper->dump($worker);
        }
    }

    public function remove(Worker $worker): void
    {
        foreach ($this->dumpers as $dumper) {
            $dumper->remove($worker);
        }
    }
}