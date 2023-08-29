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
    name: 'worker:start',
    description: 'Start one or all workers'
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
        $this->addOption('id', 'i', InputOption::VALUE_REQUIRED, 'Worker ID')
            ->addOption('all', 'a', InputOption::VALUE_NONE, 'Start all workers')
            ->addOption('async', null, InputOption::VALUE_NONE, 'Start worker async');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $id = $input->getOption('id');
        $all = $input->getOption('all');
        $async = $input->getOption('async');

        if (null !== $id && $all) {
            throw new RuntimeException('You can not use --id and --all at the same time');
        }

        if (null === $id && !$all) {
            throw new RuntimeException('You must use --id or --all');
        }

        if ($all && $async) {
            throw new RuntimeException('You can not use --all and --async at the same time');
        }

        if ($all) {
            $started = $this->workerManager->startAll();

            if ($started) {
                return Command::SUCCESS;
            }

            return Command::FAILURE;
        }

        if ($id === null) {
            throw new RuntimeException('Missing worker ID');
        }

        if (!is_numeric($id)) {
            // @todo support uuid?
            throw new RuntimeException('Worker ID must be numeric');
        }

        if ($async) {
            $started = $this->workerManager->startAsync($id);
        } else {
            $started = $this->workerManager->start($id);
        }

        if ($started) {
            return Command::SUCCESS;
        }

        return Command::FAILURE;
    }


}