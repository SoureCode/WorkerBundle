<?php

namespace SoureCode\Bundle\Worker\Command;

use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use SoureCode\Bundle\Worker\Entity\Worker;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Command\ConsumeMessagesCommand as BaseConsumeMessagesCommand;
use Symfony\Component\Messenger\EventListener\ResetServicesListener;
use Symfony\Component\Messenger\RoutableMessageBus;

#[AsCommand(
    name: 'worker',
    description: 'Wrapper for Messenger ConsumeMessagesCommand to be able to associate the worker'
)]
#[Autoconfigure(tags: ['monolog.logger' => ['channel' => 'worker']])]
class WorkerCommand extends BaseConsumeMessagesCommand
{
    public function __construct(
        RoutableMessageBus       $routableBus,
        ContainerInterface       $receiverLocator,
        EventDispatcherInterface $eventDispatcher,
        LoggerInterface          $logger = null,
        array                    $receiverNames = [],
        ResetServicesListener    $resetServicesListener = null,
        array                    $busIds = [],
        ContainerInterface       $rateLimiterLocator = null
    )
    {
        parent::__construct(
            $routableBus,
            $receiverLocator,
            $eventDispatcher,
            $logger,
            $receiverNames,
            $resetServicesListener,
            $busIds,
            $rateLimiterLocator
        );
    }

    protected function configure(): void
    {
        parent::configure();

        $this->addOption('id', 'i', InputOption::VALUE_REQUIRED, 'Worker ID');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $id = $input->getOption('id');

        if ($id === null) {
            throw new RuntimeException('Missing worker ID');
        }

        if (!is_numeric($id)) {
            // @todo support uuid?
            throw new RuntimeException('Worker ID must be numeric');
        }

        Worker::$currentId = (int)$id;

        return parent::execute($input, $output);
    }


}