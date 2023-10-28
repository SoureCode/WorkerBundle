<?php

namespace SoureCode\Bundle\Worker;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use SoureCode\Bundle\Daemon\Manager\DaemonManager;
use SoureCode\Bundle\Worker\Command\WorkerCommand;
use SoureCode\Bundle\Worker\Command\WorkerStartCommand;
use SoureCode\Bundle\Worker\Command\WorkerStopCommand;
use SoureCode\Bundle\Worker\Daemon\ChainDumper;
use SoureCode\Bundle\Worker\Daemon\LaunchdDumper;
use SoureCode\Bundle\Worker\Daemon\SystemdDumper;
use SoureCode\Bundle\Worker\DependencyInjection\WorkerCompilerPass;
use SoureCode\Bundle\Worker\EventSubscriber\MessengerEventSubscriber;
use SoureCode\Bundle\Worker\EventSubscriber\WorkerEventSubscriber;
use SoureCode\Bundle\Worker\Manager\WorkerManager;
use SoureCode\Bundle\Worker\MessageHandler\StartWorkerMessageHandler;
use SoureCode\Bundle\Worker\Repository\MessengerMessageRepository;
use SoureCode\Bundle\Worker\Repository\WorkerRepository;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use function Symfony\Component\DependencyInjection\Loader\Configurator\abstract_arg;
use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

class SoureCodeWorkerBundle extends AbstractBundle
{
    public function configure(DefinitionConfigurator $definition): void
    {
        // @formatter:off
        $definition->rootNode()
            ->children()
                ->scalarNode('service_directory')
                    ->defaultValue('%kernel.project_dir%/etc/daemons/worker')
                ->end()
            ->end();
        // @formatter:on
    }

    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $services = $container->services();
        $parameters = $container->parameters();

        $parameters->set('soure_code.worker.service_directory', $config['service_directory']);

        $services->set('soure_code.worker.dumper.launchd', LaunchdDumper::class)
            ->args([
                service('filesystem'),
                param('kernel.project_dir'),
                param('soure_code.worker.service_directory'),
            ])
            ->tag('soure_code.worker.dumper');

        $services->set('soure_code.worker.dumper.systemd', SystemdDumper::class)
            ->args([
                service('filesystem'),
                param('kernel.project_dir'),
                param('soure_code.worker.service_directory'),
            ])
            ->tag('soure_code.worker.dumper');

        $services->set('soure_code.worker.dumper.chain', ChainDumper::class)
            ->args([
                tagged_iterator('soure_code.worker.dumper'),
            ]);

        $services->set('soure_code.worker.doctrine.worker_event_subscriber', WorkerEventSubscriber::class)
            ->args([
                service('soure_code.worker.manager.worker'),
            ])
            ->tag('doctrine.event_listener', [
                'event' => 'postRemove',
            ]);

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
                service('soure_code.worker.repository.worker'),
                abstract_arg('global_failure_transport'),
                abstract_arg('failure_transports_locator'),
                abstract_arg('receiver_names'),
                abstract_arg('bus_ids'),
                service(ClockInterface::class),
                service(SerializerInterface::class),
                service('soure_code.worker.dumper.chain')
            ])
            ->tag('monolog.logger', [
                'channel' => 'worker',
            ]);

        $services
            ->alias(WorkerManager::class, 'soure_code.worker.manager.worker')
            ->public();

        $services->set('soure_code.worker.event_subscriber.messenger', MessengerEventSubscriber::class)
            ->args([
                service('logger'),
                service(ClockInterface::class),
                service(EntityManagerInterface::class),
                service('soure_code.worker.repository.worker'),
            ])
            ->tag('monolog.logger', [
                'channel' => 'worker',
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
    }

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new WorkerCompilerPass());
    }


}