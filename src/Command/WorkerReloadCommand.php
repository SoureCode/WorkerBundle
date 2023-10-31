<?php

namespace SoureCode\Bundle\Worker\Command;

use SoureCode\Bundle\Worker\Manager\WorkerManager;
use SoureCode\Bundle\Worker\Repository\WorkerRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'worker:reload',
    description: 'Reloads the worker daemons',
)]
class WorkerReloadCommand extends Command
{

    public function __construct(
        private readonly WorkerManager    $workerManager,
        private readonly WorkerRepository $workerRepository,
    )
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $workers = $this->workerRepository->findAll();

        $success = [];

        foreach ($workers as $worker) {
            if ($this->workerManager->isRunning($worker)) {
                $success[] = $this->workerManager->reload($worker);
            }
        }

        return in_array(false, $success, true) ? Command::FAILURE : Command::SUCCESS;
    }


}