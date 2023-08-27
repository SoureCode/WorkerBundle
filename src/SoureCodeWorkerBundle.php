<?php

namespace SoureCode\Bundle\Worker;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use SoureCode\Bundle\Daemon\Manager\DaemonManager;
use SoureCode\Bundle\Worker\Command\WorkerCommand;
use SoureCode\Bundle\Worker\Command\WorkerStartCommand;
use SoureCode\Bundle\Worker\Command\WorkerStopCommand;
use SoureCode\Bundle\Worker\DependencyInjection\WorkerCompilerPass;
use SoureCode\Bundle\Worker\EventSubscriber\MessengerEventSubscriber;
use SoureCode\Bundle\Worker\Manager\WorkerManager;
use SoureCode\Bundle\Worker\MessageHandler\StartWorkerMessageHandler;
use SoureCode\Bundle\Worker\Repository\MessengerMessageRepository;
use SoureCode\Bundle\Worker\Repository\WorkerRepository;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use function Symfony\Component\DependencyInjection\Loader\Configurator\abstract_arg;
use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

class SoureCodeWorkerBundle extends AbstractBundle
{
    public function configure(DefinitionConfigurator $definition): void
    {
        // @formatter:off
        $definition->rootNode()
            ->children()
            ->end();
        // @formatter:on
    }

    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $services = $container->services();

        $services->set('soure_code.worker.repository.worker', WorkerRepository::class)
            ->args([
                service('doctrine'),
            ])
            ->tag('doctrine.repository_service');

        $services
            ->alias(WorkerRepository::class, 'soure_code.worker.repository.worker')
            ->public();

        $services->set('soure_code.worker.repository.messenger_message', MessengerMessageRepository::class)
            ->args([
                service('doctrine'),
            ])
            ->tag('doctrine.repository_service');

        $services
            ->alias(MessengerMessageRepository::class, 'soure_code.worker.repository.messenger_message')
            ->public();

        $services->set('soure_code.worker.manager.worker', WorkerManager::class)
            ->args([
                service(DaemonManager::class),
                service(EntityManagerInterface::class),
                service(LoggerInterface::class),
                service('soure_code.worker.repository.worker'),
                param('kernel.project_dir'),
                abstract_arg('global_failure_transport'),
                abstract_arg('failure_transports_locator'),
                abstract_arg('receiver_names'),
                abstract_arg('bus_ids'),
                service(MessageBusInterface::class),
                service(ClockInterface::class),
            ]);

        $services
            ->alias(WorkerManager::class, 'soure_code.worker.manager.worker')
            ->public();

        $services->set('soure_code.worker.event_subscriber.messenger', MessengerEventSubscriber::class)
            ->args([
                service(ClockInterface::class),
                service(LoggerInterface::class),
                service(EntityManagerInterface::class),
                service('soure_code.worker.repository.worker'),
                service('soure_code.worker.repository.messenger_message'),
                service(SerializerInterface::class),
                service(DaemonManager::class),
            ])
            ->tag('kernel.event_subscriber');

        $services->set('soure_code.worker.command.worker', WorkerCommand::class)
            ->args([
                abstract_arg('0'),
                abstract_arg('1'),
                abstract_arg('2'),
                abstract_arg('3'),
                abstract_arg('4'),
                abstract_arg('5'),
                abstract_arg('6'),
                abstract_arg('7'),
            ])
            ->tag('monolog.logger', [
                'channel' => 'worker',
            ])
            ->tag('console.command', [
                'command' => 'worker',
            ]);

        $services->set('soure_code.worker.command.worker.start', WorkerStartCommand::class)
            ->args([
                service('soure_code.worker.manager.worker'),
            ])
            ->tag('console.command', [
                'command' => 'worker:start',
            ]);

        $services->set('soure_code.worker.command.worker.stop', WorkerStopCommand::class)
            ->args([
                service('soure_code.worker.manager.worker'),
            ])
            ->tag('console.command', [
                'command' => 'worker:stop',
            ]);

        $services->set('soure_code.message_handler.start_worker', StartWorkerMessageHandler::class)
            ->args([
                service('soure_code.worker.manager.worker'),
            ])
            ->tag('messenger.message_handler');

        $services->set('soure_code.message_handler.stop_worker', StartWorkerMessageHandler::class)
            ->args([
                service('soure_code.worker.manager.worker'),
            ])
            ->tag('messenger.message_handler');
    }

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new WorkerCompilerPass());
    }


}