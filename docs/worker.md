
# Command: worker

## Usage

```shell
Description:
  Wrapper for Messenger ConsumeMessagesCommand to be able to associate the worker

Usage:
  worker [options] [--] [<receivers>...]

Arguments:
  receivers                          Names of the receivers/transports to consume in order of priority

Options:
  -l, --limit=LIMIT                  Limit the number of received messages
  -f, --failure-limit=FAILURE-LIMIT  The number of failed messages the worker can consume
  -m, --memory-limit=MEMORY-LIMIT    The memory limit the worker can consume
  -t, --time-limit=TIME-LIMIT        The time limit in seconds the worker can handle new messages
      --sleep=SLEEP                  Seconds to sleep before asking for new messages after no messages were found [default: 1]
  -b, --bus=BUS                      Name of the bus to which received messages should be dispatched (if not passed, bus is determined automatically)
      --queues=QUEUES                Limit receivers to only consume from the specified queues (multiple values allowed)
      --no-reset                     Do not reset container services after each message
  -i, --id=ID                        Worker ID
  -h, --help                         Display help for the given command. When no command is given display help for the list command
  -q, --quiet                        Do not output any message
  -V, --version                      Display this application version
      --ansi|--no-ansi               Force (or disable --no-ansi) ANSI output
  -n, --no-interaction               Do not ask any interactive question
  -e, --env=ENV                      The Environment name. [default: "dev"]
      --no-debug                     Switch off debug mode.
  -v|vv|vvv, --verbose               Increase the verbosity of messages: 1 for normal output, 2 for more verbose output and 3 for debug

Help:
  The worker command consumes messages and dispatches them to the message bus.
  
      php /Users/jason/Projects/github/SoureCode/worker-bundle/tests/app/bin/console worker <receiver-name>
  
  To receive from multiple transports, pass each name:
  
      php /Users/jason/Projects/github/SoureCode/worker-bundle/tests/app/bin/console worker receiver1 receiver2
  
  Use the --limit option to limit the number of messages received:
  
      php /Users/jason/Projects/github/SoureCode/worker-bundle/tests/app/bin/console worker <receiver-name> --limit=10
  
  Use the --failure-limit option to stop the worker when the given number of failed messages is reached:
  
      php /Users/jason/Projects/github/SoureCode/worker-bundle/tests/app/bin/console worker <receiver-name> --failure-limit=2
  
  Use the --memory-limit option to stop the worker if it exceeds a given memory usage limit. You can use shorthand byte values [K, M or G]:
  
      php /Users/jason/Projects/github/SoureCode/worker-bundle/tests/app/bin/console worker <receiver-name> --memory-limit=128M
  
  Use the --time-limit option to stop the worker when the given time limit (in seconds) is reached.
  If a message is being handled, the worker will stop after the processing is finished:
  
      php /Users/jason/Projects/github/SoureCode/worker-bundle/tests/app/bin/console worker <receiver-name> --time-limit=3600
  
  Use the --bus option to specify the message bus to dispatch received messages
  to instead of trying to determine it automatically. This is required if the
  messages didn't originate from Messenger:
  
      php /Users/jason/Projects/github/SoureCode/worker-bundle/tests/app/bin/console worker <receiver-name> --bus=event_bus
  
  Use the --queues option to limit a receiver to only certain queues (only supported by some receivers):
  
      php /Users/jason/Projects/github/SoureCode/worker-bundle/tests/app/bin/console worker <receiver-name> --queues=fasttrack
  
  Use the --no-reset option to prevent services resetting after each message (may lead to leaking services' state between messages):
  
      php /Users/jason/Projects/github/SoureCode/worker-bundle/tests/app/bin/console worker <receiver-name> --no-reset
```


