
# DaemonBundle

## Requirements

- PHP 8.2 or higher
- Symfony 6.3 or higher

## Commands

- [`worker`](./worker.md) - The worker itself.
- [`worker:start`](./worker-start.md) - Starts a worker.
- [`worker:stop`](./worker-stop.md) - Stop one or all workers.

## Examples

```shell
# Start a worker
$ symfony console worker:start --id 1

# Stop a worker
$ symfony console worker:stop --id 1

# Stop all worker
$ symfony console worker:stop --all
```

## Installation

Make sure Composer is installed globally, as explained in the
[installation chapter](https://getcomposer.org/doc/00-intro.md)
of the Composer documentation.

### Applications that use Symfony Flex

Open a command console, enter your project directory and execute:

```console
composer require sourecode/worker-bundle
```

### Applications that don't use Symfony Flex

#### Step 1: Download the Bundle

Open a command console, enter your project directory and execute the
following command to download the latest stable version of this bundle:

```console
composer require sourecode/worker-bundle
```

#### Step 2: Enable the Bundle

Then, enable the bundle by adding it to the list of registered bundles
in the `config/bundles.php` file of your project:

```php
// config/bundles.php

return [
    // ...
    \SoureCode\Bundle\Worker\SoureCodeWorkerBundle::class => ['all' => true],
];
```