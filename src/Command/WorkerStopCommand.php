<?php

namespace SoureCode\Bundle\Worker\Command;

use RuntimeException;
use SoureCode\Bundle\Worker\Manager\WorkerManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'worker:stop',
    description: 'Stop one or more workers'
)]
class WorkerStopCommand extends Command
{
    private WorkerManager $workerManager;

    public function __construct(WorkerManager $workerManager)
    {
        $this->workerManager = $workerManager;

        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('id', 'i', InputOption::VALUE_OPTIONAL, 'Worker ID')
            ->addOption('all', 'a', InputOption::VALUE_NONE, 'Stop all workers')
            ->addOption('async', null, InputOption::VALUE_NONE, 'Stop worker async');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $id = $input->getOption('id');
        $all = $input->getOption('all');
        $async = $input->getOption('async');

        if ($id !== null && $all) {
            throw new RuntimeException('You cannot specify both --id and --all');
        }

        if ($id === null && !$all) {
            throw new RuntimeException('You must specify either --id or --all');
        }

        if ($all && $async) {
            throw new RuntimeException('You cannot specify both --all and --async');
        }

        if ($all) {
            return $this->workerManager->stopAll();
        }

        if ($id === null) {
            throw new RuntimeException('Missing worker ID');
        }

        if (!is_numeric($id)) {
            // @todo support uuid?
            throw new RuntimeException('Worker ID must be numeric');
        }

        if ($async) {
            $result = $this->workerManager->stopAsync($id);

            if ($result !== true) {
                return $result;
            }

            return Command::SUCCESS;
        }

        return $this->workerManager->stop($id);
    }
}