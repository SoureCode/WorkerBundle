
# Command: worker:stop

## Usage

```shell
Description:
  Stop one or more workers

Usage:
  worker:stop [options]

Options:
  -i, --id[=ID]            Worker ID
  -t, --timeout[=TIMEOUT]  Timeout in seconds
  -s, --signal[=SIGNAL]    Signals to send (multiple values allowed)
  -a, --all                Stop all workers
      --by-files           Stop workers by their pid files
      --async              Stop worker async
  -h, --help               Display help for the given command. When no command is given display help for the list command
  -q, --quiet              Do not output any message
  -V, --version            Display this application version
      --ansi|--no-ansi     Force (or disable --no-ansi) ANSI output
  -n, --no-interaction     Do not ask any interactive question
  -e, --env=ENV            The Environment name. [default: "dev"]
      --no-debug           Switch off debug mode.
  -v|vv|vvv, --verbose     Increase the verbosity of messages: 1 for normal output, 2 for more verbose output and 3 for debug
```


