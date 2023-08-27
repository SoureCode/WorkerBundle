# Async

The start and stop actions can be used async, to make ui a bit more responsive.
The only drawback is that a single worker has to be running to handle the messages.

Depending on the return type of the `startAsync` and `stopAsync` methods, you will know if the worker was/will be started or stopped asynchronously.
It will return `true` if the worker was started/stopped asynchronously, otherwise it is the exit code of the sync command.

## Configuration

Add the following messages to your `config/packages/messenger.yaml` file:

```yaml
framework:
  messenger:
    routing:
      SoureCode\Bundle\Worker\Message\StartWorkerMessage: async
      SoureCode\Bundle\Worker\Message\StopWorkerMessage: async
```

## Usage

Now you can start and stop workers async:

```php
<?php

use SoureCode\Bundle\Worker\Manager\WorkerManager;

/* @var WorkerManager $workerManager */

// Starts a worker async, if possible.
$workerManager->startAsync(1);

// Stops a worker async, if possible.
$workerManager->stopAsync(1);
```

Or in commands:

```shell
# Start a worker
$ symfony console worker:start --id 1 --async

# Stop a worker
$ symfony console worker:stop --id 1 --async
```