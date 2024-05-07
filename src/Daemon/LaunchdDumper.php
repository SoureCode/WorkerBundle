<?php

namespace SoureCode\Bundle\Worker\Daemon;

use SoureCode\Bundle\Worker\Entity\Worker;

class LaunchdDumper extends AbstractDumper
{
    private const TEMPLATE = <<<EOF
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
  <dict>
    <key>Label</key>
    <string>dev.sourecode.worker.{{WORKER_ID}}</string>
    <key>ProgramArguments</key>
    {{COMMAND_ARGUMENTS}}
    <key>RunAtLoad</key>
    <true/>
    <key>KeepAlive</key>
    <true/>
    <key>ThrottleInterval</key>
    <integer>1</integer>
    <key>Disabled</key>
    <true/>
    <key>ExitTimeOut</key>
    <integer>1</integer>
    <key>WorkingDirectory</key>
    <string>{{PROJECT_DIRECTORY}}</string>
    <key>StandardOutPath</key>
    <string>{{LOGS_DIRECTORY}}/soure_code_worker_{{WORKER_ID}}.log</string>
    <key>StandardErrorPath</key>
    <string>{{LOGS_DIRECTORY}}/soure_code_worker_{{WORKER_ID}}.log</string>
  </dict>
</plist>
EOF;

    public function dump(Worker $worker): void
    {
        $this->ensureLogsDirectory();

        $command = $this->getCommand($worker);

        $contents = $this->compile([
            "{{WORKER_ID}}" => $worker->getId(),
            "{{COMMAND_ARGUMENTS}}" => $this->buildArguments($command),
            "{{PROJECT_DIRECTORY}}" => $this->projectDirectory,
            "{{LOGS_DIRECTORY}}" => $this->logsDirectory,
        ], self::TEMPLATE);

        $this->dumpFile($worker, '.plist', $contents);

        $serviceFilePath = $this->getServiceFilePath($worker);
        $this->filesystem->chmod($serviceFilePath . '.plist', 0644);
    }

    private function buildArguments(array $arguments): string
    {
        $lines = [
            "<array>",
        ];

        foreach ($arguments as $argument) {
            $lines[] = sprintf("      <string>%s</string>", $argument);
        }

        $lines[] = "    </array>";

        return implode("\n", $lines);
    }

    public function remove(Worker $worker): void
    {
        $serviceFilePath = $this->getServiceFilePath($worker);

        $this->removeFile($serviceFilePath . '.plist');
    }
}
