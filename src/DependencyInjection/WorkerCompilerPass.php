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
        ]);
    }
}