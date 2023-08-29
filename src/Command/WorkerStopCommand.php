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
            ->addOption('timeout', 't', InputOption::VALUE_OPTIONAL, 'Timeout in seconds')
            ->addOption('signal', 's', InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, 'Signals to send')
            ->addOption('all', 'a', InputOption::VALUE_NONE, 'Stop all workers')
            ->addOption('by-files', null, InputOption::VALUE_NONE, 'Stop workers by their pid files')
            ->addOption('async', null, InputOption::VALUE_NONE, 'Stop worker async');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $id = $input->getOption('id') ?? null;
        $all = $input->getOption('all');
        $async = $input->getOption('async');
        $byFiles = $input->getOption('by-files');
        $timeout = $input->getOption('timeout') ?? 10;
        $signals = $input->getOption('signal') ?? null;

        if (empty($signals)) {
            $signals = null;
        }

        if ($id !== null && $all) {
            throw new RuntimeException('You cannot specify both --id and --all');
        }

        if ($id === null && !$all) {
            throw new RuntimeException('You must specify either --id or --all');
        }

        if ($all && $async) {
            throw new RuntimeException('You cannot specify both --all and --async');
        }

        if ($byFiles && $async) {
            throw new RuntimeException('You cannot specify both --by-files and --async');
        }

        if ($byFiles && $id) {
            throw new RuntimeException('You cannot specify both --by-files and --id');
        }

        if ($all) {
            $stopped = $this->workerManager->stopAll($byFiles ?? false, $timeout, $signals);

            if ($stopped) {
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
            $stopped = $this->workerManager->stopAsync($id, $timeout, $signals);
        } else {
            $stopped = $this->workerManager->stop($id, $timeout, $signals);
        }

        if ($stopped) {
            return Command::SUCCESS;
        }

        return Command::FAILURE;
    }
}