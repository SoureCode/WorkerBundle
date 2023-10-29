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
StandardOutput=append:{{PROJECT_DIRECTORY}}/soure_code_worker_{{WORKER_ID}}.log
StandardError=append:{{LOGS_DIRECTORY}}/soure_code_worker_{{WORKER_ID}}.log

[Install]
WantedBy=default.target
EOF;

    public function dump(Worker $worker): void
    {
        $this->ensureLogsDirectory();

        $command = $this->getCommand($worker);

        $contents = $this->compile([
            "{{WORKER_ID}}" => $worker->getId(),
            "{{COMMAND}}" => implode(' ', $command),
            "{{PROJECT_DIRECTORY}}" => $this->projectDirectory,
            "{{LOGS_DIRECTORY}}" => $this->logsDirectory,
        ], self::TEMPLATE);

        $this->dumpFile($worker, '.service', $contents);
    }

    public function remove(Worker $worker): void
    {
        $serviceFilePath = $this->getServiceFilePath($worker);

        $this->removeFile($serviceFilePath . '.service');
    }
}