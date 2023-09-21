<?php

use SoureCode\Bundle\Worker\Tests\app\src\EventSubscriber\OnMessageListener;
use SoureCode\Bundle\Worker\Tests\app\src\MessageHandler\SleepMessageHandler;
use SoureCode\Bundle\Worker\Tests\app\src\MessageHandler\StopMessageHandler;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container) {
    $services = $container->services();

    $services
        ->set(SleepMessageHandler::class)
        ->args([
            service('logger')
        ])
        ->tag('messenger.message_handler');

    $services
        ->set(StopMessageHandler::class)
        ->args([
            service('logger')
        ])
        ->tag('messenger.message_handler');

    $services
        ->set(OnMessageListener::class)
        ->tag('kernel.event_subscriber');
};