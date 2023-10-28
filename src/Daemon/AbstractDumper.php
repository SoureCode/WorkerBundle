<?php

namespace SoureCode\Bundle\Worker\Daemon;

use SoureCode\Bundle\Worker\Entity\Worker;
use SoureCode\Bundle\Worker\Manager\WorkerManager;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Process\PhpExecutableFinder;

abstract class AbstractDumper implements DumperInterface
{
    public function __construct(
        protected readonly Filesystem $filesystem,
        protected readonly string     $projectDirectory,
        protected readonly string     $serviceDirectory,
    )
    {
    }

    protected function getCommand(Worker $worker): array
    {
        return [
            ...$this->getPhpBinary(),
            $this->getConsolePath(),
            ...$worker->getCommand(),
        ];
    }

    protected function getPhpBinary(): ?array
    {
        $executableFinder = new PhpExecutableFinder();
        $php = $executableFinder->find(false);

        if (false === $php) {
            return null;
        }

        return array_merge([$php], $executableFinder->findArguments());
    }

    protected function getConsolePath(): string
    {
        return Path::join($this->projectDirectory, 'bin', 'console');
    }

    protected function dumpFile(Worker $worker, string $extension, string $contents): void
    {
        $serviceFilePath = $this->getServiceFilePath($worker);

        $this->filesystem->dumpFile($serviceFilePath . $extension, $contents);
    }

    protected function getServiceFilePath(Worker $worker): string
    {
        $daemonId = WorkerManager::getDaemonId($worker->getId());

        return Path::join($this->serviceDirectory, $daemonId);
    }

    protected function compile(array $parameters, string $template): string
    {
        return str_replace(array_keys($parameters), array_values($parameters), $template);
    }

    protected function removeFile($filePath): void
    {
        if ($this->filesystem->exists($filePath)) {
            $this->filesystem->remove($filePath);
        }
    }
}