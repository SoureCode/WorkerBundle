<?php

use SoureCode\Bundle\Worker\Tests\app\src\MessageHandler\SleepMessageHandler;
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
};