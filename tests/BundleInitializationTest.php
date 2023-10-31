<?php

namespace SoureCode\Bundle\Worker\Tests;

class BundleInitializationTest extends AbstractBaseTest
{
    public function testInitBundle(): void
    {
        // Get the test container
        $container = self::getContainer();

        // Test if your services exists
        $this->assertTrue($container->has('soure_code.worker.command.worker.start'), 'Command is registered');
        $this->assertTrue($container->has('soure_code.worker.command.worker.stop'), 'Command is registered');
        $this->assertTrue($container->has('soure_code.worker.command.worker.reload'), 'Command is registered');
        $this->assertTrue($container->has('soure_code.worker.command.worker.restart'), 'Command is registered');
    }
}
