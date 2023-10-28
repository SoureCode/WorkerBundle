<?php

namespace SoureCode\Bundle\Worker\Tests;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Nyholm\BundleTest\TestKernel;
use SoureCode\Bundle\Daemon\SoureCodeDaemonBundle;
use SoureCode\Bundle\Worker\SoureCodeWorkerBundle;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Bundle\MonologBundle\MonologBundle;
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
}