<?php

namespace SoureCode\Bundle\Worker\Command;

use SoureCode\Bundle\Worker\Manager\WorkerManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'worker:start',
    description: 'Starts a worker'
)]
class WorkerStartCommand extends Command
{
    private WorkerManager $workerManager;

    public function __construct(WorkerManager $workerManager)
    {
        $this->workerManager = $workerManager;

        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('id', 'i', InputOption::VALUE_REQUIRED, 'Worker ID');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $id = $input->getOption('id');

        if ($id === null) {
            throw new \RuntimeException('Missing worker ID');
        }

        if (!is_numeric($id)) {
            // @todo support uuid?
            throw new \RuntimeException('Worker ID must be numeric');
        }

        return $this->workerManager->start($id);
    }


}