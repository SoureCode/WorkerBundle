<?php

namespace SoureCode\Bundle\Worker\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class WorkerCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $sourceDefinition = $container->getDefinition('console.command.messenger_consume_messages');
        $targetDefinition = $container->getDefinition('soure_code.worker.command.worker');

        $targetDefinition->setArguments([
            $sourceDefinition->getArgument(0),
            $sourceDefinition->getArgument(1),
            $sourceDefinition->getArgument(2),
            $sourceDefinition->getArgument(3),
            $sourceDefinition->getArgument(4),
            $sourceDefinition->getArgument(5),
            $sourceDefinition->getArgument(6),
            $sourceDefinition->getArgument(7),
            $sourceDefinition->getArgument(8),
        ]);

        // index 1 argument of 'console.command.messenger_failed_messages_retry' // failure_transports_locator
        // index 4 argument of 'console.command.messenger_consume_messages' // receiver_names
        // index 6 argument of 'console.command.messenger_consume_messages' // bus_ids
        // inject all these into the worker manager
        $retryDefinition = $container->getDefinition('console.command.messenger_failed_messages_retry');
        $workerManagerDefinition = $container->getDefinition('soure_code.worker.manager.worker');

        $workerManagerDefinition->setArgument(3, $retryDefinition->getArgument(0));
        $workerManagerDefinition->setArgument(4, $retryDefinition->getArgument(1));
        $workerManagerDefinition->setArgument(5, $sourceDefinition->getArgument(4));
        $workerManagerDefinition->setArgument(6, $sourceDefinition->getArgument(6));
    }
}
