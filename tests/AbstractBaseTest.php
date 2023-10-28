<?php

namespace SoureCode\Bundle\Worker\Tests;

use Closure;
use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Nyholm\BundleTest\TestKernel;
use RuntimeException;
use SoureCode\Bundle\Daemon\SoureCodeDaemonBundle;
use SoureCode\Bundle\Worker\SoureCodeWorkerBundle;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\HttpKernel\KernelInterface;

abstract class AbstractBaseTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    protected static function createKernel(array $options = []): KernelInterface
    {
        /**
         * @var TestKernel $kernel
         */
        $kernel = parent::createKernel($options);
        $kernel->addTestBundle(DoctrineBundle::class);
        $kernel->addTestBundle(SoureCodeDaemonBundle::class);
        $kernel->addTestBundle(SoureCodeWorkerBundle::class);
        $kernel->setTestProjectDir(Path::join(__DIR__, 'app'));
        $kernel->addTestConfig(Path::join(__DIR__, 'app', 'services.php'));
        $kernel->addTestConfig(Path::join(__DIR__, 'app', 'config.yaml'));
        $kernel->handleOptions($options);

        return $kernel;
    }

    protected function waitUntil(Closure $closure, int $timeout = 5): void
    {
        $iteration = 0;

        while (true) {
            if ($closure()) {
                return;
            }

            if ($iteration > $timeout) {
                throw new RuntimeException('Timeout');
            }

            $iteration++;
            sleep(1);
        }
    }
}