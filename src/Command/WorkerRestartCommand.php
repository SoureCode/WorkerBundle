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
    name: 'worker:restart',
    description: 'Restarts all or a specific worker'
)]
class WorkerRestartCommand extends Command
{
    private WorkerManager $workerManager;

    public function __construct(WorkerManager $workerManager)
    {
        $this->workerManager = $workerManager;

        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('id', 'i', InputOption::VALUE_REQUIRED, 'Worker ID')
            ->addOption('all', 'a', InputOption::VALUE_NONE, 'All workers');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $id = $input->getOption('id');
        $all = $input->getOption('all');

        if (null !== $id && $all) {
            throw new RuntimeException('You can not use --id and --all at the same time');
        }

        if (null === $id && !$all) {
            throw new RuntimeException('You must use --id or --all');
        }

        if ($all) {
            return $this->workerManager->restartAll() ? Command::SUCCESS : Command::FAILURE;
        }

        if ($id === null) {
            throw new RuntimeException('Missing worker ID');
        }

        if (!is_numeric($id)) {
            // @todo support uuid?
            throw new RuntimeException('Worker ID must be numeric');
        }

        return $this->workerManager->restart($id) ? Command::SUCCESS : Command::FAILURE;
    }


}