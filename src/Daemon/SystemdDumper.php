<?php

namespace SoureCode\Bundle\Worker\Daemon;

use SoureCode\Bundle\Worker\Entity\Worker;

class SystemdDumper extends AbstractDumper
{
    private const TEMPLATE = <<<EOF
[Unit]
Description=SoureCode Worker {{WORKER_ID}}

[Service]
ExecStart={{COMMAND}}
Restart=always
RestartSec=10
TimeoutStopSec=10
WorkingDirectory={{PROJECT_DIRECTORY}}

[Install]
WantedBy=default.target
EOF;

    public function dump(Worker $worker): void
    {
        $command = $this->getCommand($worker);

        $contents = $this->compile([
            "{{WORKER_ID}}" => $worker->getId(),
            "{{COMMAND}}" => implode(' ', $command),
            "{{PROJECT_DIRECTORY}}" => $this->projectDirectory,
        ], self::TEMPLATE);

        $this->dumpFile($worker, '.system', $contents);
    }

    public function remove(Worker $worker): void
    {
        $serviceFilePath = $this->getServiceFilePath($worker);

        $this->removeFile($serviceFilePath . '.system');
    }
}