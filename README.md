# DeclarativeQueue #

A module adding explicit, declarative queue arguments to `magento/framework-message-queue`.

## The problem ##

Magento core creates queues only as a side effect of `queue_topology.xml` bindings and takes the queue's arguments
from the binding (magento/magento2#26966), so the very same arguments end up on **both** the queue and the binding.
There's no clean way to declare a queue's own `durable`/`autoDelete`/arguments (e.g. `x-dead-letter-exchange`,
`x-message-ttl`) independently of whatever happens to bind to it, and a queue with no binding at all can't be
declared via core alone.

## What this module does ##

* queues derived from bindings are created **without arguments** — binding `<arguments>` stay on the binding only
* queues are declared explicitly, with their own `durable`, `autoDelete` and `<arguments>`, in a new
`etc/queue_declaration.xml` file (schema `urn:magento:module:BKubicki_DeclarativeQueue:etc/queue_declaration.xsd`)
* `queue_declaration.xml` files from all modules are merged by `name` + `connection`
* a declared queue takes precedence over, and is created in addition to, whatever its bindings would otherwise derive
* a declared queue doesn't need a binding at all

```xml
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:module:BKubicki_DeclarativeQueue:etc/queue_declaration.xsd">
    <queue name="create_entity" connection="amqp" durable="true" autoDelete="false">
        <arguments>
            <argument name="x-dead-letter-exchange" xsi:type="string">entity.dead_letter</argument>
        </arguments>
    </queue>
</config>
```

See [`docs/queue-declaration-example`](docs/queue-declaration-example) for a runnable example module with an
end-to-end verification script (`e2e.sh`) that checks the resulting queue/binding arguments against a real RabbitMQ.

## Prerequisites ##

* Magento 2.4.7+ (tested against 2.4.8)
* PHP 8.1/8.2/8.3
* RabbitMQ 3.8+ (tested against 4.1)

## Installing ##

```
composer require bkubicki/declarative-queue
bin/magento module:enable BKubicki_DeclarativeQueue
bin/magento setup:upgrade
```

## Background ##

This module was extracted from [bkubicki/message-queue](https://github.com/bartoszkubicki/message-queue) (commit
`0a7ad2f612d65543d0c9e91b5a98acd85c1ab93d`) into its own standalone package, so it can be used independently of that
module's other, unrelated functionality (custom consumer envelope callbacks).

## Versioning ##

We use [SemVer](http://semver.org/) for versioning. For the versions available, see the
[tags on this repository](https://github.com/bartoszkubicki/declarative-queue/tags).

## Authors ##

* **Bartosz Kubicki** - *Initial work, fixes & maintenance* - [bartoszkubicki](https://github.com/bartoszkubicki)

See also the list of [contributors](https://github.com/bartoszkubicki/declarative-queue/contributors) who
participated in this project.

## License ##

This project is licensed under the MIT License - see the [LICENSE.md](LICENSE.md) file for details
