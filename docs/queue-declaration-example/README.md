# Queue declaration example

Example module for `bkubicki/declarative-queue` showing explicit queue declaration (`etc/queue_declaration.xml`).

* `etc/queue_topology.xml` has exchanges and bindings only
* `etc/queue_declaration.xml` has the queues with their arguments
* a binding argument stays on the binding and never reaches the queue
* a queue without declaration is created plain: the `x-message-ttl` of its binding is not applied to it
* a declared queue does not need a binding

`e2e.sh` installs the topology with `setup:upgrade` and checks queue and binding arguments through the RabbitMQ management
API. Run it from the Magento root of an environment with RabbitMQ:

```
bash app/code/BKubicki/QueueDeclarationExample/e2e.sh
```
