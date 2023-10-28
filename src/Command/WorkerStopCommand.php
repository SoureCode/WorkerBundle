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
        $this
            ->addOption('id', 'i', InputOption::VALUE_OPTIONAL, 'Worker ID')
            ->addOption('all', 'a', InputOption::VALUE_NONE, 'Stop all workers');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $id = $input->getOption('id');
        $all = $input->getOption('all');
        $gracefully = $input->getOption('gracefully');

        if ($id !== null && $all) {
            throw new RuntimeException('You cannot specify both --id and --all');
        }

        if ($id === null && !$all) {
            throw new RuntimeException('You must specify either --id or --all');
        }

        if ($all) {
            return $this->workerManager->stopAll() ? Command::SUCCESS : Command::FAILURE;
        }

        if ($id === null) {
            throw new RuntimeException('Missing worker ID');
        }

        if (!is_numeric($id)) {
            // @todo support uuid?
            throw new RuntimeException('Worker ID must be numeric');
        }

        return $this->workerManager->stop($id) ? Command::SUCCESS : Command::FAILURE;
    }
}